<?php

namespace Baril\Smoothie\Console;

use Baril\Smoothie\Relations\BelongsToManyOrdered;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class FixPositionsCommand extends Command
{
    protected $signature = 'smoothie:fix-positions {model : The model class.} {relationName? : The relationship to fix.}';
    protected $description = 'Rebuild the position column for a given orderable model or relation';

    protected $chunks = 200;

    public function handle()
    {
        $model = $this->argument('model');
        $relationName = $this->argument('relationName');
        if (!class_exists($model) || !is_subclass_of($model, Model::class)) {
            $this->error($model . ' is not a valid model class!');
            return;
        }
        $instance = new $model;

        // If the relation name is provided, then we're fixing an ordered relation:
        if ($relationName) {
            $this->fixRelation($instance, $relationName);
            return;
        }
        // else, it's an orderable model.

        // Let's check that the model uses the Orderable trait:
        if (!method_exists($model, 'getOrderColumn')) {
            $this->error('{model} must be a valid model class and use the Orderable trait!');
            return;
        }

        // The treatment will be different whether the model is groupable or not:
        if ($instance->getGroupColumn()) {
            $this->fixGroupable($instance);
        } else {
            $this->fixUngroupable($instance);
        }
        $this->info('Done!');
    }

    protected function fixUngroupable($instance)
    {
        $this->fixPositions($instance->newQuery()->ordered(), $instance->getOrderColumn());
    }

    protected function fixGroupable($instance)
    {
        $column = (array) $instance->getGroupColumn();
        $query = $instance->newQueryWithoutScopes()->getQuery()
                ->distinct()
                ->select($column);
        foreach ($column as $col) {
            $query->orderBy($col);
        }
        $query->chunk($this->chunks, function ($groups) use ($instance, $column) {
            foreach ($groups as $item) {
                $group = [];
                foreach ($column as $col) {
                    $group[] = $item->$col;
                }
                $query = $instance->newQuery()->inGroup($group)->ordered();
                $this->fixPositions($query, $instance->getOrderColumn());
                $this->line('<info>Fixed for group:</info> ' . implode(',', $group));
            }
        });
    }

    protected function fixRelation($instance, $relationName)
    {
        try {
            $relation = $instance->$relationName();
            if (!$relation instanceof BelongsToManyOrdered) {
                $this->error($relationName . ' is not a valid belongs-to-many-ordered relationship!');
                return;
            }
        } catch (\Exception $e) {
            $this->error($relationName . ' is not a valid relationship!');
            return;
        }

        $query = $instance->newQuery()
                ->select($instance->getKeyName())
                ->orderBy($instance->getKeyName());
        $query->chunk($this->chunks, function ($items) use ($relationName) {
            foreach ($items as $item) {
                $relation = $item->$relationName();
                $related = $relation->ordered()->get();
                $rownum = 0;
                $related->each(function($item) use ($relation, &$rownum) {
                    $id = $item->{$relation->getPivotAccessor()}->{$relation->getRelatedPivotKeyName()};
                    $relation->updateExistingPivot($id, [
                        $relation->getOrderColumn() => ++$rownum,
                    ], false);
                });
                $this->line("<info>Fixed for model:</info> {$item->getKey()}");
            }
        });
    }

    protected function fixPositions($query, $column)
    {
        $connection = $query->getConnection();
        $connection->statement("set @rownum := 0");
        $query->update([$column => $connection->raw('(@rownum := @rownum + 1)')]);
    }
}
