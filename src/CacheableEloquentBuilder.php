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
        if (!$this->query->usesCache || $columns !== ['*']) {
            return parent::get($columns);
        }

        $class = get_class($this->getModel());
        $results = $class::allFromCache();
        foreach ($this->query->collectionCallbacks as $arguments) {
            $method = array_shift($arguments);
            $results = $results->$method(...$arguments);
        }
        return $results;
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
