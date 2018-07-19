<?php

namespace Baril\Smoothie;

use Illuminate\Database\Eloquent\Model as LaravelModel;

class Model extends LaravelModel
{
    use Concerns\AliasesAttributes;
    use Concerns\HasFuzzyDates;
    use Concerns\HasMultiManyRelationships;
    use Concerns\HasMutualSelfRelationships;
    use Concerns\HasOrderedRelationships;
    use Concerns\ScopesTimestamps;

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
}
