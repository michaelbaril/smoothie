<?php

namespace Baril\Smoothie\Relations;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MultiPivot extends Pivot
{
    protected $multiRelations = [];

    public function setMultiRelations($relations)
    {
        $this->multiRelations = $relations;
        return $this;
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
        if (array_key_exists($method, $this->multiRelations)) {
            array_unshift($parameters, $this);
            return call_user_func_array($this->multiRelations[$method], $parameters);
        }

        return parent::__call($method, $parameters);
    }

    /**
     * Get a relationship.
     *
     * @param  string  $key
     * @return mixed
     */
    public function getRelationValue($key)
    {
        if (null !== ($value = parent::getRelationValue($key))) {
            return $value;
        }

        if (array_key_exists($key, $this->multiRelations)) {
            return $this->getRelationshipFromMethod($key);
        }
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @param  bool  $exists
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        return parent::newInstance($attributes, $exists)->setMultiRelations($this->multiRelations);
    }
}
