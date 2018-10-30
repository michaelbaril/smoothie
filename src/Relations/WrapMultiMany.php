<?php

namespace Baril\Smoothie\Relations;

use Baril\Smoothie\Relations\MultiPivot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class WrapMultiMany extends HasMany
{
    /**
     * The multi-many relationships that are "wrapped" into $this.
     *
     * @var Collection
     */
    protected $relations;

    /**
     * The intermediate table for the relation.
     *
     * @var string
     */
    protected $table;

    /**
     * The primary key of the pivot table.
     *
     * @var string
     */
    protected $pivotKey;

    /**
     * Indicates if timestamps are available on the pivot table.
     *
     * @var bool
     */
    public $withTimestamps = false;

    /**
     * The custom pivot table column for the created_at timestamp.
     *
     * @var string
     */
    protected $pivotCreatedAt;

    /**
     * The custom pivot table column for the updated_at timestamp.
     *
     * @var string
     */
    protected $pivotUpdatedAt;

    public function __construct(Collection $relations, Builder $query, Model $parent, $foreignKey, $localKey)
    {
        $this->relations = $relations;
        parent::__construct($query, $parent, $foreignKey, $localKey);
        $query->getModel()->setMultiRelations($this->getBelongsToRelations());
        $this->table = $query->getModel()->getTable();
        $this->pivotKey = $query->getModel()->getKeyName();
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
     * Get the intermediate table for the relationships.
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
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
        list($createdAt, $updatedAt) = [$this->createdAt(), $this->updatedAt()];
        $results->each(function($pivot) use ($relations, $createdAt, $updatedAt) {
            $pivot->setMultiRelations($relations);
            if ($this->withTimestamps) {
                $pivot->withTimestamps($createdAt, $updatedAt);
            }
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

    /**
     * Specify that the pivot table has creation and update timestamps.
     *
     * @param  mixed  $createdAt
     * @param  mixed  $updatedAt
     * @return $this
     */
    public function withTimestamps($createdAt = null, $updatedAt = null)
    {
        $this->withTimestamps = true;

        $this->pivotCreatedAt = $createdAt;
        $this->pivotUpdatedAt = $updatedAt;

        $this->query->getModel()->withTimestamps($this->createdAt(), $this->updatedAt());

        return $this;
    }

    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function createdAt()
    {
        return $this->pivotCreatedAt ?: $this->parent->getCreatedAtColumn();
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string
     */
    public function updatedAt()
    {
        return $this->pivotUpdatedAt ?: $this->parent->getUpdatedAtColumn();
    }

    /**
     * Get all of the IDs from the given mixed value.
     *
     * @param  mixed  $value
     * @return array
     */
    public function parseIds($value)
    {
        if ($value instanceof MultiPivot || is_array($value) && count($value) && !is_numeric(key($value))) {
            $value = [$value];
        }
        return collect($value)->mapWithKeys(function ($item, $key) {
            if ($item instanceof MultiPivot) {
                $item = $item->getAttributes();
            }
            $ids = [];
            $this->relations->each(function ($relation, $name) use ($item, &$ids) {
                $key = $relation->getRelatedPivotKeyName();
                if (array_key_exists($name, $item)) {
                    $ids[$key] = $item[$name] instanceof Model ? $item[$name]->getAttribute($relation->getRelatedKeyName()) : $item[$name];
                } elseif (array_key_exists($key, $item)) {
                    $ids[$key] = $item[$key];
                }
            });
            return [$key => $ids];
        })->toArray();
    }

    /**
     * Attach a model to the parent.
     *
     * @param  mixed  $id
     * @param  array  $attributes
     * @param  bool   $touch
     * @return void
     */
    public function attach($id, array $attributes = [])
    {
        // Here we will insert the attachment records into the pivot table. Once we have
        // inserted the records, we will touch the relationships if necessary and the
        // function will return. We can parse the IDs before inserting the records.
        $this->query->insert($this->formatAttachRecords(
            $this->parseIds($id), $attributes
        ));
    }

    /**
     * Create an array of records to insert into the pivot table.
     *
     * @param  array  $ids
     * @param  array  $attributes
     * @return array
     */
    protected function formatAttachRecords($ids, $attributes)
    {
        return collect($ids)->map(function ($pivot) use ($attributes) {
            return $this->addTimestampsToAttachment(array_merge($this->cleanRecord($attributes), $pivot, [$this->getForeignKeyName() => $this->getParentKey()]));
        })->toArray();
    }

    /**
     * Set the creation and update timestamps on an attach record.
     *
     * @param  array  $record
     * @param  bool   $exists
     * @return array
     */
    protected function addTimestampsToAttachment(array $record, $exists = false)
    {
        $fresh = $this->parent->freshTimestamp();

        if (! $exists && $this->withTimestamps) {
            $record[$this->createdAt()] = $fresh;
        }

        if ($this->withTimestamps) {
            $record[$this->updatedAt()] = $fresh;
        }

        return $record;
    }

    protected function cleanRecord($record)
    {
        if ($record instanceof MultiPivot) {
            $record = $record->getAttributes();
        }

        $attributesToClean = array_merge(
            [
                $this->pivotKey,
                $this->foreignKey
            ], $this->relations->keys()->all()
        );

        foreach ($attributesToClean as $attribute) {
            if (array_key_exists($attribute, $record)) {
                unset($record[$attribute]);
            }
        }

        return $record;
    }

    /**
     * Detach models from the relationship.
     *
     * @param  mixed  $ids
     * @param  bool  $touch
     * @return int
     */
    public function detach($ids = null)
    {
        $query = clone $this->query;

        // If associated IDs were passed to the method we will only delete those
        // associations, otherwise all of the association ties will be broken.
        // We'll return the numbers of affected rows when we do the deletes.
        if (! is_null($ids)) {
            $ids = $this->parseIds($ids);

            if (empty($ids)) {
                return 0;
            }

            $query->where(function ($where) use ($ids) {
                foreach ($ids as $pivot) {
                    $where->orWhere(function ($nestedWhere) use ($pivot) {
                        foreach ($pivot as $key => $value) {
                            $nestedWhere->where($key, $value);
                        }
                    });
                }
            });
        }

        // Once we have all of the conditions set on the statement, we are ready
        // to run the delete on the pivot table.
        $results = $query->delete();

        return $results;
    }

    /**
     * Sync the intermediate tables with a list of IDs without detaching.
     *
     * @param  \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|array  $ids
     * @return array
     */
    public function syncWithoutDetaching($ids)
    {
        return $this->sync($ids, false);
    }

    /**
     * Sync the intermediate tables with a list of IDs or collection of models.
     *
     * @param  \Illuminate\Database\Eloquent\Collection|\Illuminate\Support\Collection|array  $ids
     * @param  bool   $detaching
     * @return array
     */
    public function sync($ids, $detaching = true)
    {
        $changes = [
            'attached' => [], 'detached' => [], 'updated' => [],
        ];

        // First we need to attach any of the associated models that are not currently
        // in this joining table. We'll spin through the given IDs, checking to see
        // if they exist in the array of current ones, and if not we will insert.
        $current = $this->parseIds($this->get()->keyBy($this->pivotKey));
        $parsedIds = $this->parseIds($ids);

        $detach = collect($current)->reject(function ($item) use ($parsedIds) {
            return in_array($item, $parsedIds);
        })->toArray();

        // Next, we will take the differences of the currents and given IDs and detach
        // all of the entities that exist in the "current" array but are not in the
        // array of the new IDs given to the method which will complete the sync.
        if ($detaching && count($detach) > 0) {
            $this->detach($detach);

            $changes['detached'] = array_values($detach);
        }

        if ($ids instanceof MultiPivot || is_array($ids) && count($ids) && !is_numeric(key($ids))) {
            $ids = [$ids];
        }
        foreach ($ids as $record) {
            $pivotId = $this->findExisting($record, $current);
            if ($pivotId) {
                $query = clone $this->query;
                $cleanedRecord = $this->addTimestampsToAttachment($this->cleanRecord($record), true);
                if ($cleanedRecord) {
                    $query->where($this->table . '.' . $this->pivotKey, $pivotId)->update($cleanedRecord);
                    $changes['updated'][] = $record;
                }
            } else {
                $this->attach($record, $this->cleanRecord($record));
                $changes['attached'][] = $record;
            }
        }
        $changes['updated'] = $this->parseIds($changes['updated']);
        $changes['attached'] = $this->parseIds($changes['attached']);

        return $changes;
    }

    protected function findExisting($record, $current)
    {
        $ids = $this->parseIds($record)[0];
        return array_search($ids, $current);
    }
}
