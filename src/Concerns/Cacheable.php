<?php

namespace Baril\Smoothie\Concerns;

use Baril\Smoothie\CacheableEloquentBuilder;
use Illuminate\Contracts\Cache\Repository as Cache;

trait Cacheable
{
    protected static $cachedMethods = [
        'first',
        'find',
        'findMany',
        'findOrFail',
        'findOrNew',
        'firstOrNew',
        'firstOrCreate',
        'firstOrFail',
        'firstOr',
        'pluck',
    ];
    protected static $cacheTags = ['setup'];

    /** @var Cache */
    protected static $cache;

    public static function bootCacheable()
    {
        static::saved(function ($item) {
            static::clearCache();
        });
        static::deleted(function ($item) {
            static::clearCache();
        });
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (in_array($method, ['increment', 'decrement'])) {
            return $this->$method(...$parameters);
        }
        if (in_array($method, static::$cachedMethods)) {
            return $this->newQuery()->usingCache()->$method(...$parameters);
        }
        return $this->newQuery()->$method(...$parameters);
    }

    /**
     * @param Cache $cache
     * @return void
     */
    public static function setCache(Cache $cache)
    {
        static::$cache = $cache;
    }

    /**
     * @return Cache
     */
    public static function getCache()
    {
        if (static::$cache === null) {
            static::$cache = app(Cache::class);
        }
        return static::$cache;
    }

    /**
     * Get all of the models from the database or cache (if possible).
     *
     * @param mixed $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function all($columns = ['*'])
    {
        if ($columns !== ['*']) {
            return parent::all($columns);
        }
        return static::allFromCache();
    }

    /**
     * Get all of the models from the cache.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function allFromCache()
    {
        return static::getCache()->tags(static::$cacheTags)->rememberForever(static::class, function () {
            return static::loadFromDatabase();
        });
    }

    /**
     * Get all of the models from the dabase.
     * Override this method for example if you need to store eagerly loaded
     * relations in the cache.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected static function loadFromDatabase()
    {
        return parent::all();
    }

    /**
     * Clears the cache for this table.
     *
     * @return bool
     */
    public static function clearCache()
    {
        return static::getCache()->tags(static::$cacheTags)->forget(static::class);
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder($query)
    {
        return new CacheableEloquentBuilder($query);
    }
}
