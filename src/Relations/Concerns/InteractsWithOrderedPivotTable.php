<?php

namespace Baril\Smoothie\Relations\Concerns;

use Baril\Smoothie\GroupException;
use Baril\Smoothie\PositionException;
use Illuminate\Database\Eloquent\Model;

trait InteractsWithOrderedPivotTable
{
    protected $orderColumn;

    /**
     * @param string $orderColumn
     * @return $this
     */
    protected function setOrderColumn($orderColumn)
    {
        $this->orderColumn = $orderColumn;
        return $this->withPivot($orderColumn)->ordered();
    }

    /**
     * @return string
     */
    public function getOrderColumn()
    {
        return $this->orderColumn;
    }

    /**
     * @return string
     */
    public function getQualifiedOrderColumn()
    {
        return $this->table . '.' . $this->orderColumn;
    }

    public function getMaxPosition()
    {
        return $this->newPivotQuery(false)->max($this->getOrderColumn());
    }

    public function getNextPosition()
    {
        return $this->getMaxPosition() + 1;
    }

    /**
     * @param string $direction
     * @return $this
     */
    public function ordered($direction = 'asc')
    {
        return $this->unordered()->orderBy($this->getQualifiedOrderColumn(), $direction);
    }

    /**
     * @return $this
     */
    public function unordered()
    {
        $query = $this->query->getQuery();
        $query->orders = collect($query->orders)
                ->reject(function ($order) {
                    return isset($order['column'])
                           ? $order['column'] === $this->getQualifiedOrderColumn() : false;
                })->values()->all();
        return $this;
    }

    /**
     * Create a new query builder for the pivot table.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newPivotQuery($ordered = true)
    {
        $query = parent::newPivotQuery();
        return $ordered ? $query->orderBy($this->getQualifiedOrderColumn()) : $query;
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    protected function newPivotQueryBetween($leftPosition, $rightPosition)
    {
        $query = $this->newPivotQuery();

        if (!is_null($leftPosition)) {
            $query->where($this->getQualifiedOrderColumn(), '>', $leftPosition);
        }
        if (!is_null($rightPosition)) {
            $query->where($this->getQualifiedOrderColumn(), '<', $rightPosition);
        }
        return $query;
    }

    /**
     * Extract the pivot (with the order column) from a model, or fetch it
     * from the database.
     *
     * @param \Illuminate\Database\Eloquent\Model $entity
     * @return \Illuminate\Database\Eloquent\Pivot
     *
     * @throws GroupException
     */
    protected function parsePivot($entity)
    {
        if ($entity->{$this->accessor} && $entity->{$this->accessor}->{$this->orderColumn}) {
            if ($entity->{$this->accessor}->{$this->foreignPivotKey} !== $this->parent->{$this->parentKey}) {
                throw new GroupException('The provided model doesn\'t belong to this relationship!');
            }
            return $entity->{$this->accessor};
        }

        $pivot = $this->newPivotStatementForId($entity->getKey())->first();
        if ($pivot === null) {
            throw new GroupException('The provided model doesn\'t belong to this relationship!');
        }
        return $pivot;
    }

    /**
     * Moves the provided model to the specific offset.
     *
     * @param Model $entity
     * @param int $newOffset
     * @return $this
     *
     * @throws GroupException
     * @throws PositionException
     */
    public function moveToOffset($entity, $newOffset)
    {
        $pivot = $this->parsePivot($entity);
        $count = $this->newPivotQuery()->count();

        if ($newOffset < 0) {
            $newOffset = $count + $newOffset;
        }
        if ($newOffset < 0 || $newOffset >= $count) {
            throw new PositionException("Invalid offset $newOffset!");
        }

        $oldOffset = $this->newPivotQueryBetween(null, $pivot->{$this->orderColumn})->count();

        if ($oldOffset === $newOffset) {
            return $this;
        }

        $entity = (object) [
            $this->accessor => $pivot,
        ];
        $positionEntity = (object) [
            $this->accessor => $this->newPivotQuery()->offset($newOffset)->first(),
        ];
        if ($oldOffset < $newOffset) {
            return $this->moveAfter($entity, $positionEntity);
        } else {
            return $this->moveBefore($entity, $positionEntity);
        }
    }

    /**
     * Moves the provided model to the first position.
     *
     * @param Model $entity
     * @return $this
     *
     * @throws GroupException
     */
    public function moveToStart($entity)
    {
        return $this->moveToOffset($entity, 0);
    }

    /**
     * Moves the provided model to the first position.
     *
     * @param Model $entity
     * @return $this
     *
     * @throws GroupException
     */
    public function moveToEnd($entity)
    {
        return $this->moveToOffset($entity, -1);
    }

    /**
     * Moves the provided model to the specified position.
     *
     * @param Model $entity
     * @param int $newPosition
     * @return $this
     *
     * @throws GroupException
     * @throws PositionException
     */
    public function moveToPosition($entity, $newPosition)
    {
        return $this->moveToOffset($entity, $newPosition - 1);
    }

    /**
     *
     * @param Model $entity
     * @param int $positions
     * @param boolean $strict
     */
    public function moveUp($entity, $positions = 1, $strict = true)
    {
        $pivot = $this->parsePivot($entity);
        $orderColumn = $this->getOrderColumn();
        $currentPosition = $pivot->$orderColumn;
        $newPosition = $currentPosition - $positions;
        if (!$strict) {
            $newPosition = max(1, $newPosition);
            $newPosition = min($this->getMaxPosition(), $newPosition);
        }
        return $this->moveToPosition($entity, $newPosition);
    }

    /**
     *
     * @param Model $entity
     * @param int $positions
     * @param boolean $strict
     */
    public function moveDown($entity, $positions = 1, $strict = true)
    {
        return $this->moveUp($entity, -$positions, $strict);
    }

    /**
     * Swaps the provided models.
     *
     * @param Model $entity1
     * @param Model $entity2
     * @return $this
     *
     * @throws GroupException
     */
    public function swap($entity1, $entity2)
    {
        $pivot1 = $this->parsePivot($entity1);
        $pivot2 = $this->parsePivot($entity2);

        $this->getConnection()->transaction(function () use ($pivot1, $pivot2) {
            $relatedPivotKey = $this->relatedPivotKey;
            $orderColumn = $this->orderColumn;

            if ($pivot1->$orderColumn === $pivot2->$orderColumn) {
                return;
            }

            $this->updateExistingPivot($pivot1->$relatedPivotKey, [$orderColumn => $pivot2->$orderColumn]);
            $this->updateExistingPivot($pivot2->$relatedPivotKey, [$orderColumn => $pivot1->$orderColumn]);
        });
        return $this;
    }

    /**
     * Moves $entity after $positionEntity.
     *
     * @param Model $entity
     * @param Model $positionEntity
     * @return $this
     *
     * @throws GroupException
     */
    public function moveAfter($entity, $positionEntity)
    {
        return $this->move('moveAfter', $entity, $positionEntity);
    }

    /**
     * Moves $entity before $positionEntity.
     *
     * @param Model $entity
     * @param Model $positionEntity
     * @return $this
     *
     * @throws GroupException
     */
    public function moveBefore($entity, $positionEntity)
    {
        return $this->move('moveBefore', $entity, $positionEntity);
    }

    /**
     * @param string $action moveAfter/moveBefore
     * @param Model  $entity
     * @param Model  $positionEntity
     *
     * @throws GroupException
     */
    protected function move($action, $entity, $positionEntity)
    {
        $pivot = $this->parsePivot($entity);
        $positionPivot = $this->parsePivot($positionEntity);

        $this->getConnection()->transaction(function () use ($pivot, $positionPivot, $action) {
            $relatedPivotKey = $this->relatedPivotKey;
            $orderColumn = $this->orderColumn;

            $oldPosition = $pivot->$orderColumn;
            $newPosition = $positionPivot->$orderColumn;

            if ($oldPosition === $newPosition) {
                return;
            }

            $isMoveBefore = $action === 'moveBefore'; // otherwise moveAfter
            $isMoveForward = $oldPosition < $newPosition;

            if ($isMoveForward) {
                $this->newPivotQueryBetween($oldPosition, $newPosition)->decrement($orderColumn);
            } else {
                $this->newPivotQueryBetween($newPosition, $oldPosition)->increment($orderColumn);
            }

            $this->updateExistingPivot($pivot->$relatedPivotKey, [$orderColumn => $this->getNewPosition($isMoveBefore, $isMoveForward, $newPosition)]);
            $this->updateExistingPivot($positionPivot->$relatedPivotKey, [$orderColumn => $this->getNewPosition(!$isMoveBefore, $isMoveForward, $newPosition)]);
        });
        return $this;
    }

    /**
     * @param bool $isMoveBefore
     * @param bool $isMoveForward
     * @param      $position
     *
     * @return mixed
     */
    protected function getNewPosition($isMoveBefore, $isMoveForward, $position)
    {
        if (!$isMoveBefore) {
            ++$position;
        }

        if ($isMoveForward) {
            --$position;
        }

        return $position;
    }

    /**
     * Attach all of the records that aren't in the given current records.
     *
     * @param  array  $records
     * @param  array  $current
     * @param  bool   $touch
     * @return array
     */
    protected function attachNew(array $records, array $current, $touch = true)
    {
        $i = 0;
        $changes = ['attached' => [], 'updated' => []];

        foreach ($records as $id => $attributes) {
            $attributes[$this->orderColumn] = ++$i;

            // If the ID is not in the list of existing pivot IDs, we will insert a new pivot
            // record, otherwise, we will just update this existing record on this joining
            // table, so that the developers will easily update these records pain free.
            if (! in_array($id, $current)) {
                parent::attach($id, $attributes, $touch);

                $changes['attached'][] = $this->castKey($id);
            }

            // Now we'll try to update an existing pivot record with the attributes that were
            // given to the method. If the model is actually updated we will add it to the
            // list of updated pivot records so we return them back out to the consumer.
            elseif (count($attributes) > 0 &&
                $this->updateExistingPivot($id, $attributes, $touch)) {
                $changes['updated'][] = $this->castKey($id);
            }
        }

        return $changes;
    }

    /**
     * Attach a model to the parent.
     *
     * @param mixed $id
     * @param array $attributes
     * @param bool  $touch
     */
    public function attach($id, array $attributes = [], $touch = true)
    {
        $ids = $this->parseIds($id);
        $nextPosition = $this->getNextPosition();
        foreach ($ids as $id) {
            $attributes[$this->orderColumn] = $nextPosition++;
            parent::attach($id, $attributes, $touch);
        }
    }

    /**
     * Detach models from the relationship.
     *
     * @param  mixed  $ids
     * @param  bool  $touch
     * @return int
     */
    public function detach($ids = null, $touch = true)
    {
        $results = parent::detach($ids, $touch);
        if ($results) {
            $this->refreshPositions();
        }
        return $results;
    }

    /**
     *
     * @param array|\Illuminate\Database\Eloquent\Collection $ids
     * @return $this
     */
    public function setOrder($ids)
    {
        $models = $this->get()->sortByKeys($this->parseIds($ids));

        $ids = $models->modelKeys();
        $positions = $models->pluck($this->accessor . '.' . $this->getOrderColumn())->sort()->values()->all();
        $newOrder = array_combine($ids, $positions);

        $this->getConnection()->transaction(function () use ($newOrder) {
            foreach ($newOrder as $id => $position) {
                $this->newPivotStatementForId($id)->update([$this->getOrderColumn() => $position]);
            }
        });

        return $this;
    }

    public function refreshPositions()
    {
        $connection = $this->getConnection();
        $connection->transaction(function() use ($connection) {
            $connection->statement('set @rownum := 0');
            $this->newPivotQuery()->orderBy($this->orderColumn)->update([$this->orderColumn => $connection->raw('(@rownum := @rownum + 1)')]);
        });
    }

    public function before($entity)
    {
        $pivot = $this->parsePivot($entity);
        return $this->query->cloneWithout(['orders'])
                ->orderBy($this->getQualifiedOrderColumn(), 'desc')
                ->where($this->getQualifiedOrderColumn(), '<', $pivot->{$this->orderColumn});
    }

    public function after($entity)
    {
        $pivot = $this->parsePivot($entity);
        return $this->query->cloneWithout(['orders'])
                ->orderBy($this->getQualifiedOrderColumn())
                ->where($this->getQualifiedOrderColumn(), '>', $pivot->{$this->orderColumn});
    }
}
