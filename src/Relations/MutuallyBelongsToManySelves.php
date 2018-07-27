<?php

namespace Baril\Smoothie\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MutuallyBelongsToManySelves extends BelongsToMany
{
    /**
     * Create a new belongs to many relationship instance.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  string  $table
     * @param  string  $firstPivotKey
     * @param  string  $secondPivotKey
     * @param  string  $parentKey
     * @param  string  $relationName
     * @return void
     */
    public function __construct(
        Builder $query,
        $parent,
        $table,
        $firstPivotKey,
        $secondPivotKey,
        $parentKey,
        $relationName = null
    ) {

        parent::__construct(
            $query,
            $parent,
            $table,
            $firstPivotKey,
            $secondPivotKey,
            $parentKey,
            $parentKey,
            $relationName
        );
    }

    /**
     * Set the join clause for the relation query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|null  $query
     * @return $this
     */
    protected function performJoin($query = null)
    {
        $query = $query ?: $this->query;

        // We need to join to the intermediate table on the related model's primary
        // key column with the intermediate table's foreign key for the related
        // model instance. Then we can set the "where" for the parent models.
        $baseTable = $this->related->getTable();

        $key = $baseTable.'.'.$this->relatedKey;

        $query->join($this->table, function ($join) use ($key) {
            $join->on($key, '=', $this->getQualifiedRelatedPivotKeyName())
                 ->orOn($key, '=', $this->getQualifiedForeignPivotKeyName());
        });

        return $this;
    }

    /**
     * Set the where clause for the relation query.
     *
     * @return $this
     */
    protected function addWhereConstraints()
    {
        $this->query->where(function ($query) {
            $query->where($this->getQualifiedForeignPivotKeyName(), '=', $this->parent->{$this->parentKey})
                  ->orWhere($this->getQualifiedRelatedPivotKeyName(), '=', $this->parent->{$this->parentKey});
        })->where(function ($query) {
            // Exclude self from resultset (unless the model is actually self-attached):
            $query->where($this->related->getTable() . '.' . $this->relatedKey, '!=', $this->parent->{$this->parentKey})
                  ->orWhereRaw($this->getQualifiedForeignPivotKeyName() . '=' . $this->getQualifiedRelatedPivotKeyName());
        });

        return $this;
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $this->query->where(function ($query) use ($models) {
            $keys = $this->getKeys($models, $this->parentKey);
            $query->whereIn($this->getQualifiedForeignPivotKeyName(), $keys)
                  ->orWhereIn($this->getQualifiedRelatedPivotKeyName(), $keys);
        });
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $results
     * @return array
     */
    protected function buildDictionary(Collection $results)
    {
        // First we will build a dictionary of child models keyed by the foreign key
        // of the relation so that we will easily and quickly match them to their
        // parents without having a possibly slow inner loops for every models.
        $dictionary = [];

        foreach ($results as $result) {
            if ($result->{$this->relatedKey} == $result->{$this->accessor}->{$this->relatedPivotKey}) {
                $dictionary[$result->{$this->accessor}->{$this->foreignPivotKey}][] = $result;
            } else {
                $dictionary[$result->{$this->accessor}->{$this->relatedPivotKey}][] = $result;
            }
        }

        return $dictionary;
    }

    /**
     * Create a new pivot attachment record.
     *
     * @param  int   $id
     * @param  bool  $timed
     * @return array
     */
    protected function baseAttachRecord($id, $timed)
    {
        $record[$this->relatedPivotKey] = max($id, $this->parent->{$this->parentKey});

        $record[$this->foreignPivotKey] = min($id, $this->parent->{$this->parentKey});

        // If the record needs to have creation and update timestamps, we will make
        // them by calling the parent model's "freshTimestamp" method which will
        // provide us with a fresh timestamp in this model's preferred format.
        if ($timed) {
            $record = $this->addTimestampsToAttachment($record);
        }

        return $record;
    }

    /**
     * Detach models from the relationship.
     *
     * @param  mixed  $ids
     * @param  bool  $touch
     * @return int
     */
    public function detach($ids = null, $touch = true)
    {
        $query = $this->newPivotQuery();

        // If associated IDs were passed to the method we will only delete those
        // associations, otherwise all of the association ties will be broken.
        // We'll return the numbers of affected rows when we do the deletes.
        if (! is_null($ids)) {
            $ids = $this->parseIds($ids);

            if (empty($ids)) {
                return 0;
            }

            $query->where(function ($query) use ($ids) {
                $ids = (array) $ids;
                $parentId = $this->parent->{$this->parentKey};
                if (false !== ($k = array_search($parentId, $ids))) {
                    $query->where($this->relatedPivotKey, $parentId)
                          ->where($this->foreignPivotKey, $parentId);
                    unset($ids[$k]);
                }
                if ($ids) {
                    $query->orWhereIn($this->relatedPivotKey, $ids)
                          ->orWhereIn($this->foreignPivotKey, $ids);
                }
            });
        }

        // Once we have all of the conditions set on the statement, we are ready
        // to run the delete on the pivot table. Then, if the touch parameter
        // is true, we will go ahead and touch all related models to sync.
        $results = $query->delete();

        if ($touch) {
            $this->touchIfTouching();
        }

        return $results;
    }

    /**
     * Get a new pivot statement for a given "other" ID.
     *
     * @param  mixed  $id
     * @return \Illuminate\Database\Query\Builder
     */
    public function newPivotStatementForId($id)
    {
        $parentId = $this->parent->{$this->parentKey};
        if ($id > $parentId) {
            return $this->newPivotQueryWithoutConstraints()
                    ->where($this->foreignPivotKey, $parentId)
                    ->where($this->relatedPivotKey, $id);
        } elseif ($id < $parentId) {
            return $this->newPivotQueryWithoutConstraints()
                    ->where($this->relatedPivotKey, $parentId)
                    ->where($this->foreignPivotKey, $id);
        } else {
            // $id == $parentId
            return $this->newPivotQueryWithoutConstraints()
                    ->where($this->relatedPivotKey, $id)
                    ->where($this->foreignPivotKey, $id);
        }
    }

    /**
     * Create a new query builder for the pivot table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newPivotQuery()
    {
        $query = $this->newPivotQueryWithoutConstraints();

        return $query->where(function ($query) {
            $query->where($this->foreignPivotKey, $this->parent->{$this->parentKey})
                  ->orWhere($this->relatedPivotKey, $this->parent->{$this->parentKey});
        });
    }

    protected function newPivotQueryWithoutConstraints()
    {
        $query = $this->newPivotStatement();

        foreach ($this->pivotWheres as $arguments) {
            call_user_func_array([$query, 'where'], $arguments);
        }

        foreach ($this->pivotWhereIns as $arguments) {
            call_user_func_array([$query, 'whereIn'], $arguments);
        }

        $select = [
            "if ({$this->foreignPivotKey} = ?, {$this->foreignPivotKey}, {$this->relatedPivotKey}) as {$this->foreignPivotKey}",
            "if ({$this->foreignPivotKey} = ?, {$this->relatedPivotKey}, {$this->foreignPivotKey}) as {$this->relatedPivotKey}",
        ];
        $query->selectRaw(implode(',', $select), [$this->parent->{$this->parentKey}, $this->parent->{$this->parentKey}]);

        return $query;
    }
}
