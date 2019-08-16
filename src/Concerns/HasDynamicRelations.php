<?php

namespace Baril\Smoothie\Concerns;

trait HasDynamicRelations
{
    protected static $dynamicRelations = [];
    protected $localDynamicRelations = [];

    public function __call($method, $parameters)
    {
        if ($method == 'defineRelation') {
            list($name, $closure) = $parameters;
            $this->localDynamicRelations[$name] = $closure;
            return;
        }
        if ($this->hasRelation($method)) {
            if (array_key_exists($method, $this->localDynamicRelations)) {
                return call_user_func_array($this->localDynamicRelations[$method]->bindTo($this, static::class), $parameters);
            }
            if (array_key_exists($method, static::$dynamicRelations)) {
                return call_user_func_array(static::$dynamicRelations[$method]->bindTo($this, static::class), $parameters);
            }
        }
        return parent::__call($method, $parameters);
    }

    public static function __callStatic($method, $parameters)
    {
        if ($method == 'defineRelation') {
            list($name, $closure) = $parameters;
            static::$dynamicRelations[$name] = $closure;
            return;
        }
        return parent::__callStatic($method, $parameters);
    }

    public function hasRelation($name)
    {
        return method_exists($this, $name)
                || array_key_exists($name, static::$dynamicRelations)
                || array_key_exists($name, $this->localDynamicRelations);
    }

    /**
     * Get a relationship.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getRelationValue($key)
    {
        // If the key already exists in the relationships array, it just means the
        // relationship has already been loaded, so we'll just return it out of
        // here because there is no need to query within the relations twice.
        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }
        // If the "attribute" exists as a method on the model, we will just assume
        // it is a relationship and will load and return results from the query
        // and hydrate the relationship's value on the "relationships" array.
        if ($this->hasRelation($key)) {
            return $this->getRelationshipFromMethod($key);
        }
    }
}
