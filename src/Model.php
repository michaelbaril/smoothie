<?php

namespace Baril\Smoothie;

use Illuminate\Database\Eloquent\Model as LaravelModel;

class Model extends LaravelModel
{
    use Concerns\AliasesAttributes,
        Concerns\CachesRelationships,
        Concerns\HasFuzzyDates,
        Concerns\HasMultiManyRelationships,
        Concerns\HasMutualSelfRelationships,
        Concerns\HasOrderedRelationships,
        Concerns\ScopesTimestamps {
        Concerns\CachesRelationships::newBelongsToMultiMany insteadof Concerns\HasMultiManyRelationships;
    }

    /**
     * Save the model to the database.
     *
     * @param  array  $options
     * @return bool
     */
    public function save(array $options = [])
    {
        if (($options['restore'] ?? false) && $this->exists) {
            $this->original = $this->fresh()->original;
        }

        return parent::save($options);
    }

    /**
     * Update only the provided attributes in the database.
     *
     * @param array $attributes
     * @param array $options
     * @return boolean
     */
    public function updateOnly(array $attributes = [], array $options = [])
    {
        if (null !== ($fresh = $this->fresh()) && $fresh->update($attributes, $options)) {
            $this->fill($attributes);
            $this->original = $fresh->original;
            return true;
        }
        return false;
    }
}
