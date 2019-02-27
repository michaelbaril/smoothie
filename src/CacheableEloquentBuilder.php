<?php

namespace Baril\Smoothie;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class CacheableEloquentBuilder extends Builder
{
    public function __construct(QueryBuilder $query)
    {
        if (!($query instanceof CacheableQueryBuilder)) {
            $query = CacheableQueryBuilder::makeFrom($query);
        }
        parent::__construct($query);
    }

    public function get($columns = ['*'])
    {
        if (!$this->query->usesCache) {
            return parent::get($columns);
        }

        $class = get_class($this->getModel());
        $results = $class::allFromCache();
        $collectionCallbacks = array_merge(
            $this->query->collectionCallbacks['wheres'] ?? [],
            array_reverse($this->query->collectionCallbacks['sorts'] ?? [])
        );
        foreach ($collectionCallbacks as $arguments) {
            $method = array_shift($arguments);
            $results = $results->$method(...$arguments);
        }
        return $results;
    }

    /**
     * Get an array with the values of a given column.
     *
     * @param  string  $column
     * @param  string|null  $key
     * @return \Illuminate\Support\Collection
     */
    public function pluck($column, $key = null)
    {
        if ($this->query->usesCache) {
            $class = get_class($this->getModel());
            $results = $class::allFromCache();
            return $results->pluck($column, $key);
        }
        return parent::pluck($column, $key);
    }

    public static function makeFrom(Builder $query)
    {
        $cachedQuery = new static($query->query);
        foreach ($query as $property => $value) {
            if ($property == 'query') {
                continue;
            }
            $cachedQuery->$property = $value;
        }
        return $cachedQuery;
    }
}
