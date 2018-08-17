<?php

namespace Baril\Smoothie;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class OrderableCollection extends EloquentCollection
{
    public function saveOrder()
    {
        if ($this->isEmpty()) {
            return $this;
        }

        $instance = $this->first();
        $orderColumn = $instance->getOrderColumn();
        $groupColumn = $instance->getGroupColumn();

        // Check that all items are in the same group:
        if ($groupColumn) {
            if ($this->pluck($groupColumn)->unique()->count() > 1) {
                throw new GroupException('All models must be in same group!');
            }
        }

        // Get current positions:
        $positions = $this->pluck($orderColumn)->sort()->all();
        reset($positions);

        // Save the new order:
        $instance->getConnection()->transaction(function () use ($orderColumn, &$positions) {
            $this->values()->each(function($model) use ($orderColumn, &$positions) {
                // Update the order field without triggering the listeners:
                $model->newQuery()->whereKey($model->getKey())->update([$orderColumn => current($positions)]);
                $model->setAttribute($orderColumn, current($positions));
                next($positions);
            });
        });

        return $this;
    }
}
