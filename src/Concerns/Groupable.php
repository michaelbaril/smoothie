<?php

namespace Baril\Smoothie\Concerns;

use Baril\Smoothie\GroupException;

/**
 * @traitUses \Illuminate\Database\Eloquent\Model
 *
 * @property string $groupColumn Name of the "group" column
 */
trait Groupable
{
    /**
     * Return the name of the "group" field.
     *
     * @return string|null
     */
    public function getGroupColumn()
    {
        return $this->groupColumn ?? null;
    }

    public function getGroup($original = false)
    {
        if (is_null($this->groupColumn)) {
            return null;
        }
        if (!is_array($this->groupColumn)) {
            return $original ? $this->getOriginal($this->groupColumn) : $this->{$this->groupColumn};
        }

        $group = [];
        foreach ($this->groupColumn as $column) {
            $group[] = $original ? $this->getOriginal($column) : $this->$column;
        }
        return $group;
    }

    /**
     * Restrict the query to the provided group.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $group
     */
    public function scopeInGroup($query, $group)
    {
        $groupColumn = (array) $this->getGroupColumn();
        $group = is_null($group) ? [ null ] : array_values((array) $group);
        foreach ($group as $i => $value) {
            $query->where($groupColumn[$i], $value);
        }
    }

    /**
     * Restrict the query to the provided group.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $groups
     */
    public function scopeInGroups($query, $groups)
    {
        $query->where(function ($query) use ($groups) {
            foreach ($groups as $group) {
                $query->orWhere(function ($query) use ($group) {
                    $this->scopeInGroup($query, $group);
                });
            }
        });
    }

    /**
     * Get a new query builder for the model's group.
     *
     * @param boolean $excludeThis
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function newQueryInSameGroup($excludeThis = false)
    {
        $query = $this->newQuery();
        $groupColumn = (array) $this->getGroupColumn();
        if ($groupColumn) {
            $group = [];
            foreach ($groupColumn as $column) {
                $group[] = $this->$column;
            }
            $query->inGroup($group);
        }
        if ($excludeThis) {
            $query->whereKeyNot($this->getKey());
        }
        return $query;
    }

    /**
     * Check if $this belongs to the same group as the provided $model.
     *
     * @param static $model
     * @return bool
     *
     * @throws GroupException
     */
    public function isInSameGroupAs($model)
    {
        if (!$model instanceof static) {
            throw new GroupException('Both models must belong to the same class!');
        }
        $groupColumn = (array) $this->getGroupColumn();
        foreach ($groupColumn as $column) {
            if ($model->$column != $this->$column) {
                return false;
            }
        }
        return true;
    }

    /**
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function others()
    {
        return $this->newQueryInSameGroup(true);
    }
}
