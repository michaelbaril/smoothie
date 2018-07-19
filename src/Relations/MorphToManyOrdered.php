<?php

namespace Baril\Smoothie\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Many to many relation with ordering support.
 */
class MorphToManyOrdered extends MorphToMany
{
    use Concerns\InteractsWithOrderedPivotTable {
        newPivotQuery as _newPivotQuery;
    }

    /**
     * Create a new morph to many relationship instance.
     *
     * @param Builder $query
     * @param Model   $parent
     * @param string  $name
     * @param string  $orderColumn
     * @param string  $table
     * @param string  $foreignPivotKey
     * @param string  $relatedPivotKey
     * @param string  $parentKey
     * @param string  $relatedKey
     * @param string  $relationName
     * @param bool    $inverse
     */
    public function __construct(
        Builder $query,
        Model $parent,
        $name,
        $orderColumn,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
        $inverse = false
    ) {
        parent::__construct(
            $query,
            $parent,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName,
            $inverse
        );
        $this->setOrderColumn($orderColumn);
    }

    /**
     * Create a new query builder for the pivot table.
     *
     * @param boolean $ordered
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newPivotQuery($ordered = true)
    {
        return $this->_newPivotQuery($ordered)->where($this->morphType, $this->morphClass);
    }
}
