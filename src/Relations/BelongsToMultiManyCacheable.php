<?php

namespace Baril\Smoothie\Relations;

class BelongsToMultiManyCacheable extends BelongsToMultiMany
{
    protected $usesCache = false;
    protected $eagerModels = [];
    protected $pivots;

    public function usingCache($bool = true)
    {
        $this->usesCache = $bool;
        return $this;
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $this->eagerModels = $models;
        parent::addEagerConstraints($models);
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUnfolded($columns = ['*'])
    {
        if (!$this->usesCache || $columns !== ['*']) {
            return parent::getUnfolded($columns);
        }

        // Create pivot query:
        $pivotQuery = $this->newPivotStatement();
        foreach ($this->pivotWheres as $arguments) {
            call_user_func_array([$pivotQuery, 'where'], $arguments);
        }
        foreach ($this->pivotWhereIns as $arguments) {
            call_user_func_array([$pivotQuery, 'whereIn'], $arguments);
        }

        if ($this->pivotIds) {
            $pivotQuery->whereIn($this->getPivotKeyName(), $this->pivotIds);
        } else {
            // Retrieve parent keys:
            $parentKeys = $this->eagerModels
                    ? collect($this->eagerModels)->pluck($this->parentKey)->all()
                    : [ $this->parent->{$this->parentKey} ];
            $pivotQuery->whereIn($this->foreignPivotKey, $parentKeys);
        }

        // Execute pivot query:
        $pivots = $pivotQuery->get($this->aliasedPivotColumns());

        // Retrieve related models from cache:
        $query = clone $this->query;
        $related = $query->usingCache()->get()
                ->whereIn($this->relatedKey, $pivots->pluck('pivot_' . $this->relatedPivotKey)->all())
                ->keyBy($this->relatedKey);

        // Match pivots with related models:
        $results = [];
        foreach ($pivots as $pivot) {
            $model = $related->where($this->relatedKey, $pivot->{'pivot_' . $this->relatedPivotKey})->first();
            if (!$model) {
                continue;
            }
            $model = clone $model;
            foreach ($pivot as $key => $value) {
                $model->$key = $value;
            }
            $results[] = $model;
        }
        $this->hydratePivotRelation($results);

        // Return result:
        return $this->related->newCollection($results);
    }
}
