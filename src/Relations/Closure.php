<?php

namespace Baril\Smoothie\Relations;

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
}
