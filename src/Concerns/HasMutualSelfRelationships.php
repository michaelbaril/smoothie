<?php

namespace Baril\Smoothie\Concerns;

use Baril\Smoothie\Relations\MutuallyBelongsToManySelves;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @traitUses \Illuminate\Database\Eloquent\Model
 */
trait HasMutualSelfRelationships
{
    /**
     * Get the relationship name of the belongs to many.
     *
     * @return string
     */
    protected function guessMutualRelation()
    {
        $methods = ['guessMutualRelation', 'mutuallyBelongsToManySelves'];
        $caller = Arr::first(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), function ($trace) use ($methods) {
            return ! in_array($trace['function'], $methods);
        });

        return ! is_null($caller) ? $caller['function'] : null;
    }

    /**
     * Get the default foreign key name for the model in a self-relationship.
     *
     * @param int $num
     * @return string
     */
    public function getForeignKeyForMutualSelfRelationship($num)
    {
        return Str::snake(class_basename($this)).$num.'_'.$this->primaryKey;
    }

    /**
     * Define a mutual many-to-many relationship with the same model.
     *
     * @param  string  $related
     * @param  string  $table
     * @param  string  $firstPivotKey
     * @param  string  $secondPivotKey
     * @param  string  $parentKey
     * @param  string  $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function mutuallyBelongsToManySelves(
        $table = null,
        $firstPivotKey = null,
        $secondPivotKey = null,
        $parentKey = null,
        $relation = null
    ) {

        // If no relationship name was passed, we will pull backtraces to get the
        // name of the calling function. We will use that function name as the
        // title of this relation since that is a great convention to apply.
        if (is_null($relation)) {
            $relation = $this->guessMutualRelation();
        }

        // First, we'll need to determine the foreign keys for the
        // relationship. Once we have determined the keys we'll make the query
        // instances as well as the relationship instances we need for this.
        $firstPivotKey = $firstPivotKey ?: $this->getForeignKeyForMutualSelfRelationship(1);
        $secondPivotKey = $secondPivotKey ?: $this->getForeignKeyForMutualSelfRelationship(2);

        // If no table name was provided, we can guess it by concatenating the two
        // models using underscores in alphabetical order. The two model names
        // are transformed to snake case from their default CamelCase also.
        if (is_null($table)) {
            $table = $this->joiningTable(static::class);
        }

        return new MutuallyBelongsToManySelves(
            $this->newQuery(),
            $this,
            $table,
            $firstPivotKey,
            $secondPivotKey,
            $parentKey ?: $this->getKeyName(),
            $relation
        );
    }
}
