<?php

namespace Baril\Smoothie\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class WrapMultiMany extends HasMany
{
    protected $relations;

    public function __construct(Collection $relations, Builder $query, Model $parent, $foreignKey, $localKey)
    {
        $this->relations = $relations;
        parent::__construct($query, $parent, $foreignKey, $localKey);
        $query->getModel()->setMultiRelations($this->getBelongsToRelations());
    }

    /**
     * Magic caller for the wrapped relations.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (array_key_exists($method, $this->relations)) {
            return $this->relations[$method];
        }

        return parent::__call($method, $parameters);
    }

    /**
     * Get the results of the relationship (overriden because the original
     * method would call $query->get() directly, bypassing $this->get()).
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->get();
    }

    /**
     * Execute the query as a "select" statement, and sets the relation on
     * all the retrieved models.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get($columns = ['*'])
    {
        $results = $this->query->get($columns);
        $relations = $this->getBelongsToRelations();
        $results->each(function($pivot) use ($relations) {
            $pivot->setMultiRelations($relations);
        });
        return $results;
    }

    /**
     * Translates the wrapped multi-many-to-many relations into BelongsTo
     * relations that can be set on the pivot models.
     *
     * @return array
     */
    protected function getBelongsToRelations()
    {
        return $this->relations->mapWithKeys(function ($relation, $relationName) {
            return [$relationName => function ($pivot) use ($relation, $relationName) {
                $related = get_class($relation->getRelated());
                $foreignKey = $relation->getRelatedPivotKeyName();
                $ownerKey = $relation->getRelatedKeyName();
                return $pivot->belongsTo($related, $foreignKey, $ownerKey, $relationName);
            }];
        })->toArray();
    }
}
