<?php

namespace Baril\Smoothie\Relations;

use Baril\Smoothie\TreeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Closure extends BelongsToMany
{
    protected $depth = null;

    public function setRelationName($name)
    {
        $this->relationName = $name;
        return $this;
    }

    public function setDepth($depth)
    {
        $this->depth = $depth;
        return $this;
    }

    /**
     * Get the pivot attributes from a model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return array
     */
    protected function migratePivotAttributes(Model $model)
    {
        $values = parent::migratePivotAttributes($model);
        if (array_key_exists('depth', $values) && $this->depth !== null) {
            $values['remaining_depth'] = $this->depth - $values['depth'];
        }

        return $values;
    }

    // Since the Closure relation is read-only, all the methods below will
    // throw an exception.

    protected function readOnly()
    {
        throw new TreeException("The $this->relationName relation is read-only!");
    }

    public function save(Model $model, array $pivotAttributes = [], $touch = true)
    {
        $this->readOnly();
    }

    public function saveMany($models, array $pivotAttributes = [])
    {
        $this->readOnly();
    }

    public function create(array $attributes = [], array $joining = [], $touch = true)
    {
        $this->readOnly();
    }

    public function createMany(array $records, array $joinings = [])
    {
        $this->readOnly();
    }

    public function toggle($ids, $touch = true)
    {
        $this->readOnly();
    }

    public function syncWithoutDetaching($ids)
    {
        $this->readOnly();
    }

    public function sync($ids, $detaching = true)
    {
        $this->readOnly();
    }

    public function attach($id, array $attributes = [], $touch = true)
    {
        $this->readOnly();
    }

    public function detach($ids = null, $touch = true)
    {
        $this->readOnly();
    }
}
