<?php

namespace Baril\Smoothie;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;

class CacheableQueryBuilder extends Builder
{
    public $usesCache = false;
    public $collectionCallbacks = [];

    public function usingCache($bool = true)
    {
        $this->usesCache = $bool;
        return $this;
    }

    protected function formatColumn($column)
    {
        if (Str::startsWith($column, $this->from . '.')) {
            $column = Str::replaceFirst($this->from . '.', '', $column);
        }
        return Str::contains($column, ['.', '`']) ? null : $column;
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $formattedColumn = $this->formatColumn($column);
        if ($boolean === 'and'
                && $formattedColumn !== null
                && in_array($operator, ['=', '==', '!=', '<>', '<', '>', '<=', '>=', '===', '!=='])) {
            $this->collectionCallbacks['wheres'][] = ['where', $formattedColumn, $operator, $value];
        }
        return parent::where($column, $operator, $value, $boolean);
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        $formattedColumn = $this->formatColumn($column);
        if ($boolean === 'and' && $formattedColumn !== null) {
            $this->collectionCallbacks['wheres'][] = [$not ? 'whereNotIn' : 'whereIn', $formattedColumn, $values];
        }
        return parent::whereIn($column, $values, $boolean, $not);
    }

    public function orderBy($column, $direction = 'asc')
    {
        $formattedColumn = $this->formatColumn($column);
        if ($formattedColumn !== null) {
            $this->collectionCallbacks['sorts'][] = ['sortBy', $formattedColumn, SORT_REGULAR, $direction === 'desc'];
        }
        return parent::orderBy($column, $direction);
    }

    public static function makeFrom(Builder $query)
    {
        $cachedQuery = new static(
            $query->connection,
            $query->grammar,
            $query->processor
        );
        foreach ($query as $property => $value) {
            $cachedQuery->$property = $value;
        }
        return $cachedQuery;
    }
}
