<?php

namespace Baril\Smoothie\Concerns;

use LogicException;
use Baril\Smoothie\Relations\BelongsToMultiMany;
use Baril\Smoothie\Relations\MultiPivot as Pivot;
use Baril\Smoothie\Relations\WrapMultiMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @traitUses \Illuminate\Database\Eloquent\Model
 */
trait HasMultiManyRelationships
{
    // Other multi-relationships loaded in this model (used during matching/folding,
    // see Relations\BelongsToMultiMany::matchConstrained and
    // Relations\BelongsToMultiMany::fold):
    protected $multiRelations = [];

    public function registerMultiRelation($table, $relationName)
    {
        $this->multiRelations[$table][] = $relationName;
    }

    public function getMultiRelations($table)
    {
        return array_unique($this->multiRelations[$table] ?? []);
    }

    /**
     * Define a "multi" many-to-many relationship.
     *
     * @param  string  $related
     * @param  string  $table
     * @param  string  $pivotKey
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string  $relation
     * @return \Baril\Smoothie\Relations\BelongsToMultiMany
     */
    public function belongsToMultiMany($related, $table, $pivotKey = 'id', $foreignPivotKey = null, $relatedPivotKey = null,
                                  $parentKey = null, $relatedKey = null, $relation = null)
    {
        // If no relationship name was passed, we will pull backtraces to get the
        // name of the calling function. We will use that function name as the
        // title of this relation since that is a great convention to apply.
        if (is_null($relation)) {
            $relation = $this->guessMultiManyRelation();
        }

        // First, we'll need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we'll make the query
        // instances as well as the relationship instances we need for this.
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();

        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        // If no table name was provided, we can guess it by concatenating the two
        // models using underscores in alphabetical order. The two model names
        // are transformed to snake case from their default CamelCase also.
        if (is_null($table)) {
            $table = $this->joiningTable($related);
        }

        return $this->newBelongsToMultiMany(
            $instance->newQuery(), $this, $table, $pivotKey, $foreignPivotKey,
            $relatedPivotKey, $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(), $relation
        );
    }

   /**
     * Instantiate a new BelongsToMultiMany relationship.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Model  $parent
     * @param  string  $table
     * @param  string  $pivotKey
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string  $relationName
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    protected function newBelongsToMultiMany(
        Builder $query,
        Model $parent,
        $table,
        $pivotKey,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null
    ) {
        return new BelongsToMultiMany(
            $query, $parent, $table, $pivotKey, $foreignPivotKey,
            $relatedPivotKey, $parentKey, $relatedKey, $relationName
        );
    }

    /**
     * Get the relationship name of the belongs to many.
     *
     * @return string
     */
    protected function guessMultiManyRelation()
    {
        $methods = ['guessMultiManyRelation', 'belongsToMultiMany'];
        $caller = Arr::first(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), function ($trace) use ($methods) {
            return ! in_array($trace['function'], $methods);
        });

        return ! is_null($caller) ? $caller['function'] : null;
    }

    /**
     * "Wraps" the multi-many-to-many relationships into a one-to-many relation
     * to the pivot table.
     *
     * @param array $relations
     * @param string $foreignKey
     * @param string $localKey
     * @return \Baril\Smoothie\Relations\WrapMultiMany
     */
    public function wrapMultiMany($relations, $foreignKey = null, $localKey = null)
    {
        $relations = collect($relations)->mapWithKeys(function($relation, $name) {
            if (is_numeric($name)) {
                return [$relation => $this->$relation()];
            }
            return [$name => $relation];
        });

        $pivotTables = $relations->map(function($relation) {
            return $relation->getTable();
        })->unique();
        $primaryKeys = $relations->map(function($relation) {
            return $relation->getPivotKeyName();
        })->unique();

        if ($pivotTables->count() > 1 || $primaryKeys->count() > 1) {
            throw new \InvalidArgumentException('The provided relations can\'t be wrapped together because their definitions conflict.');
        }

        $pivotTable = $pivotTables->first();
        $primaryKey = $primaryKeys->first();
        $instance = (new Pivot)->setTable($pivotTable)->setKeyName($primaryKey);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        return new WrapMultiMany($relations, $instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * Set the multi-many-to-many relationships that should be eager loaded
     * without constraints on the pivot key.
     *
     * @param  mixed  $relations
     * @return $this
     */
    public function scopeWithAll($query, $relations)
    {
        if (is_string($relations)) {
            $relations = func_get_args();
            array_shift($relations); // removing $query from args
        }

        $relations = collect($relations)->mapWithKeys(function ($constraints, $name) {
            if (is_numeric($name)) {
                $name = $constraints;
                list($name, $constraints) = Str::contains($name, ':')
                            ? $this->createSelectWithConstraint($name)
                            : [$name, function () {
                                //
                            }];
            }

            try {
                if (strpos($name, '.') === false) {
                    $model = $this;
                    $relationName = $name;
                    $relation = $model->$relationName();
                } else {
                    $model = $this;
                    foreach(explode('.', $name) as $relationName) {
                        $relation = $model->$relationName();
                        $model = $relation->getRelated();
                    }
                }
            } catch (\Exception $e) {
                throw RelationNotFoundException::make($model, $relationName);
            }

            if (! $relation instanceof Relation) {
                throw new LogicException(sprintf(
                    '%s::%s must return a relationship instance.', get_class($model), $relationName
                ));
            }
            if ($relation instanceof BelongsToMultiMany) {
                return [$name => function($relation) use ($constraints) {
                    $relation->all();
                    $constraints($relation);
                }];
            }

            // If it's another type of relation, withAll() is just a proxy to with()
            return [$name => $constraints];
        });

        $query->with($relations->toArray());
    }

    /**
     * Create a constraint to select the given columns for the relation.
     * (copied from Eloquent\Builder)
     *
     * @param  string  $name
     * @return array
     */
    protected function createSelectWithConstraint($name)
    {
        return [explode(':', $name)[0], function ($query) use ($name) {
            $query->select(explode(',', explode(':', $name)[1]));
        }];
    }
}
