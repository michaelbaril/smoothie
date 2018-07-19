<?php

namespace Baril\Smoothie\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Many to many relation with ordering support.
 */
class BelongsToManyOrdered extends BelongsToMany
{
    use Concerns\InteractsWithOrderedPivotTable;

    /**
     * Create a new belongs to many relationship instance.
     * Sets default ordering by $orderColumn column.
     *
     * @param Builder $query
     * @param Model   $parent
     * @param string  $orderColumn
     * @param string  $table
     * @param string  $foreignPivotKey
     * @param string  $relatedPivotKey
     * @param string  $parentKey
     * @param string  $relatedKey
     * @param string  $relationName
     */
    public function __construct(
        Builder $query,
        Model $parent,
        $orderColumn,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null
    ) {
        parent::__construct($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName);
        $this->setOrderColumn($orderColumn);
    }
}
