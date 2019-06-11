<?php

namespace Baril\Smoothie\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\RelationNotFoundException;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Str;

class BelongsToMultiMany extends BelongsToMany
{
    /**
     * The primary key of the pivot table.
     *
     * @var string
     */
    protected $pivotKey;

    /**
     * The pivot ids parsed from a previously queried relation.
     * Array keys are pivot ids, array values are parent models.
     *
     * @var array
     */
    protected $pivotIds;

    /**
     * Indicates if the results must be "folded" (ie. rows in the pivot table
     * that reference the same related model are merged).
     *
     * @var boolean
     */
    protected $folded = true;

    public function __construct(Builder $query,Model $parent, $table, $pivotKey, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName = null)
    {
        $this->pivotKey = $pivotKey;
        parent::__construct($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);

        $this->withPivot($pivotKey);
        $this->setPivotValues();
    }

    /**
     * Set the where clause for the relation query.
     *
     * @return $this
     */
    protected function addWhereConstraints()
    {
        if (null !== ($values = $this->parsePivotKeys([$this->parent]))) {
            // If pivot ids were found, it means that $this->parent comes from
            // a "sibling" relation.
            // Let's add the appropriate constraints on the pivot key then:
            $this->pivotIds = $values;
            $this->addPivotConstraints($values);
        }
        return parent::addWhereConstraints();
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $values = $this->parsePivotKeys($models);
        if ($values) {
            $this->pivotIds = $values;
            $this->addPivotConstraints($values);
        } else {
            parent::addEagerConstraints($models);
        }
    }

    protected function addPivotConstraints($values, $query = null)
    {
        $query = $query ?? $this;
        $query->whereIn($this->getQualifiedPivotKeyName(), $values);
        return $query;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        // If the parent models come from a "sibling" relation, the matching
        // process will be specific:
        if ($this->pivotIds) {
            return $this->matchConstrained($models, $results, $relation);
        }

        // Else, it's like a regular BelongsToMany relationship (except that
        // the results will be folded):

        $dictionary = parent::buildDictionary($results);

        // Once we have an array dictionary of child objects we can easily match the
        // children back to their parent using the dictionary and the keys on the
        // the parent models. Then we will return the hydrated models back out.
        foreach ($models as $model) {
            if (isset($dictionary[$key = $model->{$this->parentKey}])) {
                $model->setRelation(
                    $relation, $this->related->newCollection($this->fold($dictionary[$key]))
                );
            }
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents ("constrained" version).
     *
     * @param  array   $models
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @param  string  $relation
     * @return array
     */
    protected function matchConstrained(array $models, Collection $results, $relation)
    {
        $dictionary = $this->buildDictionary($results);

        foreach ($models as $model) {
            // We need to notify the model of the existence of this relation,
            // so that it's merged properly when the parent models are folded:
            $model->registerMultiRelation($this->table, $this->relationName);

            $related = [];
            $pivots = (array) $this->parsePivotKey($model);
            foreach ($pivots as $pivot) {
                if (isset($dictionary[$pivot])) {
                    $related[] = $dictionary[$pivot];
                }
            }
            $model->setRelation(
                $relation, $this->related->newCollection($this->fold($related))
            );
        }

        return $models;
    }

    /**
     * Add the constraints for a relationship query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     * @param  array|mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        if ($parentQuery->getQuery()->from == $query->getQuery()->from) {
            return $this->getRelationExistenceQueryForSelfJoin($query, $parentQuery, $columns);
        }

        // If the pivot table is already joined in the parent query, then we
        // want to use it for our constraints instead of joining the table again:
        foreach ($parentQuery->getQuery()->joins ?? [] as $joinClause) {
            if ($joinClause->table == $this->table) {
                return $query->select($columns)->whereColumn(
                    $this->getQualifiedRelatedPivotKeyName(), '=', $this->related->getTable().'.'.$this->relatedKey
                );
            }
        }

        $this->performJoin($query);

        return $query->select($columns)->whereColumn(
            $this->getQualifiedParentKeyName(), '=', $this->getExistenceCompareKey()
        );
    }

    /**
     * Add the constraints for a relationship query on the same table.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parentQuery
     * @param  array|mixed  $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationExistenceQueryForSelfJoin(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $query->select($columns);

        $query->from($this->related->getTable().' as '.$hash = $this->getRelationCountHash());

        $this->related->setTable($hash);

        // If the pivot table is already joined in the parent query, then we
        // want to use it for our constraints instead of joining the table again:
        foreach ($parentQuery->getQuery()->joins ?? [] as $joinClause) {
            if ($joinClause->table == $this->table) {
                return $query->select($columns)->whereColumn(
                    $this->getQualifiedRelatedPivotKeyName(), '=', $this->related->getTable().'.'.$this->relatedKey
                );
            }
        }

        $this->performJoin($query);

        return $query->select($columns)->whereColumn(
            $this->getQualifiedParentKeyName(), '=', $this->getExistenceCompareKey()
        );
    }

    /**
     * Extract the existing pivot object from the provided $model.
     *
     * @param Model $model
     * @return Pivot
     */
    protected function parsePivot($model)
    {
        foreach ($model->getRelations() as $relation) {
            if ($relation instanceof Pivot && $relation->getTable() == $this->table) {
                return $relation;
            }
        }
        return null;
    }

    /**
     * Extract the pivot id (or ids) from the provided $model.
     *
     * @param Model $model
     * @return int|array|null
     */
    protected function parsePivotKey($model)
    {
        $pivot = $this->parsePivot($model);
        if (!$pivot) {
            return null;
        }

        // In case the model comes from a "folded" relation, we will need to
        // check the pivot key prefixed with an underscore as well:
        $pivotKey = '_' . $this->pivotKey;
        return $pivot->$pivotKey ?? $pivot->{$this->pivotKey};
    }

    /**
     * Extract the pivot id (or ids) from each of the provided $model.
     *
     * @param array $models
     * @return array|null
     */
    protected function parsePivotKeys($models)
    {
        $pivots = [];
        foreach ($models as $model) {
            $modelPivots = (array) $this->parsePivotKey($model);
            if (!$modelPivots) {
                return null;
            }
            $pivots = array_merge($pivots, $modelPivots);
        }
        return $pivots;
    }

    /**
     * Returns an array where the keys are the pivot ids, and the values are
     * the models.
     *
     * @param Collection $results
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        $dictionary = [];
        foreach ($results as $model) {
            $pivots = (array) $this->parsePivotKey($model);
            foreach ($pivots as $pivot) {
                $dictionary[$pivot] = $model;
            }
        }
        return $dictionary;
    }

    /**
     * "Folds" the results of the relationship (ie. identical models are merged
     * into one).
     *
     * @param array|\Illuminate\Support\Collection $unfolded
     * @return array
     */
    protected function fold($unfolded)
    {
        // We will need an array of the pivot keys for each model.
        // We can't use the actual name of the pivot key for that, because
        // it would be cast as int. Let's prefix it with an underscore.
        $pivotKey = '_' . $this->pivotKey;

        $models = [];
        foreach ($unfolded as $model) {
            $id = $model->getKey();
            if (!array_key_exists($id, $models)) {
                // It's the first time we come across this model, let's
                // put it in the result array and initialize $values with
                // an empty array:
                $models[$id] = $model;
                $values = [];
            } else {
                // We've run into this model already, $values needs to hold
                // the pivot keys that we already know:
                $values = $models[$id]->{$this->accessor}->$pivotKey;

                // Also, we need to merge the "sibling" relations properly:
                foreach ($model->getMultiRelations($this->table) as $relation) {
                    $related1 = $models[$id]->getRelationValue($relation);
                    $related2 = $model->getRelationValue($relation);
                    $models[$id]->setRelation($relation, $related1->merge($related2));
                }
            }
            // Let's push the new pivot key into $values and put the result in
            // the model:
            $values[] = $model->{$this->accessor}->{$this->pivotKey};
            $models[$id]->{$this->accessor}->$pivotKey = $values;
        }
        return array_values($models);
    }

    /**
     * "Scopes" the relation so that the results won't be folded when fetched.
     *
     * @return $this
     */
    public function unfolded()
    {
        $this->folded = false;
        return $this;
    }

    /**
     * Removes the constraints on the pivot key.
     *
     * @return $this
     */
    public function all()
    {
        if ($this->pivotIds) {
            $pivotKey = $this->getQualifiedPivotKeyName();
            $wheres =& $this->query->getQuery()->wheres;
            foreach ($wheres as $i => $where) {
                if ($where['type'] == 'In' && $where['column'] == $pivotKey) {
                    unset($wheres[$i]);
                    $wheres = array_values($wheres);
                    $this->pivotIds = null;
                    break;
                }
            }
            $this->unsetPivotValues();
        }
        return $this;
    }

    /**
     * Execute the query as a "select" statement and fold the results.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get($columns = ['*'])
    {
        $unfolded = $this->getUnfolded($columns);
        return $this->folded ? $this->related->newCollection($this->fold($unfolded)) : $unfolded;
    }

    /**
     * Execute the query as a "select" statement, without folding the results.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUnfolded($columns = ['*'])
    {
        return parent::get($columns);
    }

    /**
     * Get the relationship for eager loading. Results must be unfolded and
     * will be folded afterwards.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getEager()
    {
        return $this->getUnfolded();
    }

    /**
     * Get the related key for the relation.
     *
     * @return string
     */
    public function getRelatedKeyName()
    {
        return $this->relatedKey;
    }

    /**
     * Get the primary key of the pivot table.
     *
     * @return string
     */
    public function getPivotKeyName()
    {
        return $this->pivotKey;
    }

    /**
     * Get the fully qualified primary key of the pivot table.
     *
     * @return string
     */
    public function getQualifiedPivotKeyName()
    {
        return $this->table.'.'.$this->pivotKey;
    }

    /**
     * Constraints the relation using another multi-relation.
     *
     * @param string  $relationname
     * @param mixed  $value
     */
    public function for($relationName, $value)
    {
        if (method_exists($this->parent, $relationName)) {
            $relation = $this->parent->$relationName();
        } elseif (method_exists($this->parent, $relationNamePlural = Str::plural($relationName))) {
            $relationName = $relationNamePlural;
            $relation = $this->parent->$relationName();
        } else {
            throw RelationNotFoundException::make($this->parent, $relationName);
        }
        if ($relation->getTable() != $this->getTable()) {
            throw new \InvalidArgumentException("The relation $relationName has a different pivot table.");
        }
        if ($value instanceof Model) {
            $value = $value->getKey();
        }
        if (is_array($value) || $value instanceof BaseCollection) {
            return $this->wherePivotIn($relation->getRelatedPivotKeyName(), $this->parseIds($value));
        } else {
            return $this->withPivotValue($relation->getRelatedPivotKeyName(), $value);
        }
    }

    /**
     * Create a new query builder for the pivot table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newPivotQuery()
    {
        $query = $this->newPivotStatement();

        if ($this->pivotIds) {
            $this->addPivotConstraints($this->pivotIds, $query);
        }

        foreach ($this->pivotWheres as $arguments) {
            call_user_func_array([$query, 'where'], $arguments);
        }

        foreach ($this->pivotWhereIns as $arguments) {
            call_user_func_array([$query, 'whereIn'], $arguments);
        }

        return $query->where($this->foreignPivotKey, $this->parent->{$this->parentKey});
    }

    /**
     * Sets the pivot values for future calls to ->attach().
     */
    protected function setPivotValues()
    {
        if (null !== $pivot = $this->parsePivot($this->parent)) {
            $pivot = $pivot->toArray();
            unset($pivot[$this->pivotKey], $pivot['_' . $this->pivotKey]);
            foreach ($pivot as $column => $value) {
                $this->pivotValues[] = compact('column', 'value');
            }
        }
    }

    /**
     * Unsets the pivot values if ->all() has been called.
     */
    protected function unsetPivotValues()
    {
        if (null !== $pivot = $this->parsePivot($this->parent)) {
            $pivot = $pivot->toArray();
            unset($pivot[$this->pivotKey], $pivot['_' . $this->pivotKey]);
            foreach ($pivot as $column => $value) {
                foreach ($this->pivotValues as $i => $pivotValue) {
                    if ($pivotValue === compact('column', 'value')) {
                        unset($this->pivotValues[$i]);
                        break;
                    }
                }
            }
        }
    }
}
