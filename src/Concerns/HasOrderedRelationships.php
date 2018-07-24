<?php

namespace Baril\Smoothie\Concerns;

use Baril\Smoothie\Relations\BelongsToManyOrdered;
use Baril\Smoothie\Relations\MorphToManyOrdered;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait HasOrderedRelationships
{
    /**
     * Get the relationship name of the belongs to many.
     *
     * @return string
     */
    protected function guessOrderedRelation()
    {
        $methods = ['guessOrderedRelation', 'belongsToManyOrdered', 'morphToManyOrdered', 'morphedByManyOrdered'];
        $caller = Arr::first(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), function ($trace) use ($methods) {
            return ! in_array($trace['function'], $methods);
        });

        return ! is_null($caller) ? $caller['function'] : null;
    }

    /**
     * Define a many-to-many relationship.
     * The prototype is similar as the belongsToMany method, with the
     * $orderColumn added as the 2nd parameter.
     *
     * @param string $related
     * @param string $orderColumn
     * @param string $table
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param string $parentKey
     * @param string $relatedKey
     * @param string $relation
     *
     * @return BelongsToSortedMany
     */
    public function belongsToManyOrdered($related, $orderColumn = 'position', $table = null, $foreignPivotKey = null, $relatedPivotKey = null,
                                  $parentKey = null, $relatedKey = null, $relation = null)
    {
        // If no relationship name was passed, we will pull backtraces to get the
        // name of the calling function. We will use that function name as the
        // title of this relation since that is a great convention to apply.
        if (is_null($relation)) {
            $relation = $this->guessOrderedRelation();
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

        return new BelongsToManyOrdered(
            $instance->newQuery(),
            $this,
            $orderColumn,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(),
            $relation
        );
    }

    /**
     * Define a polymorphic ordered many-to-many relationship.
     *
     * @param  string  $related
     * @param  string  $name
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  bool  $inverse
     * @return \Baril\Smoothie\Relations\MorphToManyOrdered
     */
    public function morphToManyOrdered($related, $name, $orderColumn = 'position', $table = null, $foreignPivotKey = null,
                                $relatedPivotKey = null, $parentKey = null,
                                $relatedKey = null, $inverse = false)
    {
        $caller = $this->guessOrderedRelation();

        // First, we will need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we will make the query
        // instances, as well as the relationship instances we need for these.
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $name . '_id';

        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        // Now we're ready to create a new query builder for this related model and
        // the relationship instances for this relation. This relations will set
        // appropriate query constraints then entirely manages the hydrations.
        $table = $table ?: Str::plural($name);

        return new MorphToManyOrdered(
            $instance->newQuery(), $this, $name, $orderColumn, $table,
            $foreignPivotKey, $relatedPivotKey, $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(), $caller, $inverse
        );
    }

    /**
     * Define a polymorphic, inverse, ordered many-to-many relationship.
     * The prototype is similar as the morphedByMany method, with the
     * $orderColumn added as the 3rd parameter.
     *
     * @param string $related
     * @param string $name
     * @param string $orderColumn
     * @param string $table
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param string $parentKey
     * @param string $relatedKey
     * @return \Baril\Smoothie\Relations\MorphToManyOrdered
     */
    public function morphedByManyOrdered($related, $name, $orderColumn = 'position', $table = null, $foreignPivotKey = null,
                                  $relatedPivotKey = null, $parentKey = null, $relatedKey = null)
    {
        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();

        // For the inverse of the polymorphic many-to-many relations, we will change
        // the way we determine the foreign and other keys, as it is the opposite
        // of the morph-to-many method since we're figuring out these inverses.
        $relatedPivotKey = $relatedPivotKey ?: $name . '_id';

        return $this->morphToManyOrdered(
            $related, $name, $orderColumn, $table, $foreignPivotKey,
            $relatedPivotKey, $parentKey, $relatedKey, true
        );
    }
}
