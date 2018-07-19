<?php

namespace Baril\Smoothie\Console;

use Baril\Smoothie\Relations\MutuallyBelongsToManySelves;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class FixPivotsCommand extends Command
{
    protected $signature = 'smoothie:fix-pivots {model : The model class.} {relationName : The relationship to fix.}';
    protected $description = 'Rebuild pivot table for a mutual self-relationship';

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
        if (!method_exists($instance, $relationName)) {
            $this->error($relationName . ' is not a valid relationship!');
            return;
        }
        $relation = $instance->$relationName();
        if (!$relation || !($relation instanceof MutuallyBelongsToManySelves)) {
            $this->error($relationName . ' is not a mutually-belongs-to-many-selves relationship!');
            return;
        }

        $updatedRows = $instance->getConnection()->transaction(function () use ($relation) {
            $this->insertMissingPivots($relation);
            return $this->cleanPivots($relation);
        });

        switch ($updatedRows) {
            case 0:
                $this->info('Nothing to fix!');
                break;
            case 1:
                $this->info('Fixed pivot table, 1 row was fixed');
                break;
            default:
                $this->info("Fixed pivot table, $updatedRows rows were fixed");
                break;
        }
    }

    protected function insertMissingPivots(MutuallyBelongsToManySelves $relation)
    {
        $firstKey = $relation->getForeignPivotKeyName();
        $secondKey = $relation->getRelatedPivotKeyName();
        $statement = $relation->newPivotStatement();
        $relation->newPivotStatement()->orderBy($firstKey)->chunk($this->chunks, function($pivots) use ($firstKey, $secondKey, $statement) {
            $pivotsToInsert = [];
            foreach ($pivots as $pivot) {
                if ($pivot->$firstKey > $pivot->$secondKey) {
                    $pivotsToInsert[] = array_merge((array) $pivot, [
                        $firstKey => $pivot->$secondKey,
                        $secondKey => $pivot->$firstKey,
                    ]);
                }
            }
            $statement->insert($pivotsToInsert);
        });
    }

    protected function cleanPivots(MutuallyBelongsToManySelves $relation)
    {
        $firstKey = $relation->getForeignPivotKeyName();
        $secondKey = $relation->getRelatedPivotKeyName();
        $statement = $relation->newPivotStatement();
        return $statement->whereRaw($firstKey . ' > ' . $secondKey)->delete();
    }
}
