<?php

namespace Baril\Smoothie\Relations;

use Illuminate\Database\Eloquent\Relations\Pivot;

class MultiPivot extends Pivot
{
    protected $multiRelations = [];
    public $incrementing = true;
    public $timestamps = false;
    protected $createdAt = self::CREATED_AT;
    protected $updatedAt = self::UPDATED_AT;

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

    /**
     * Specify that the pivot table has creation and update timestamps.
     *
     * @param  mixed  $createdAt
     * @param  mixed  $updatedAt
     * @return $this
     */
    public function withTimestamps($createdAt = null, $updatedAt = null)
    {
        $this->timestamps = true;
        $this->createdAt = $createdAt ?? $this->createdAt;
        $this->updatedAt = $updatedAt ?? $this->updatedAt;

        return $this;
    }

    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function getCreatedAtColumn()
    {
        return $this->createdAt;
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string
     */
    public function getUpdatedAtColumn()
    {
        return $this->updatedAt;
    }
}
