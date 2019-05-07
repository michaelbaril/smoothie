<?php

namespace Baril\Smoothie\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class FixTreeCommand extends Command
{
    protected $signature = 'smoothie:fix-tree {model : The model class.}';
    protected $description = 'Rebuilds the closures for a given tree';

    public function handle()
    {
        $model = $this->input->getArgument('model');
        if (!class_exists($model) || !is_subclass_of($model, Model::class) || !method_exists($model, 'getClosureTable')) {
            $this->error('{model} must be a valid model class and use the BelongsToTree trait!');
            return;
        }

        $this->rebuildClosures($model);
    }

    protected function rebuildClosures($model)
    {
        $instance = new $model;
        $connection = $instance->getConnection();
        $connection->transaction(function () use ($instance, $connection) {
            $table = $instance->getTable();
            $parentKey = $instance->getParentForeignKeyName();
            $primaryKey = $instance->getKeyName();
            $closureTable = $instance->getClosureTable();

            // Delete old closures:
            $connection->table($closureTable)->truncate();

            // Insert "self-closures":
            $connection->insert("
                INSERT INTO $closureTable (ancestor_id, descendant_id, depth)
                SELECT $primaryKey, $primaryKey, 0 FROM $table");

            // Increment depth and insert closures until there's nothing left to insert:
            $depth = 1;
            $continue = true;
            while ($continue) {
                $connection->insert("
                    INSERT INTO $closureTable (ancestor_id, descendant_id, depth)
                    SELECT closure_table.ancestor_id, main_table.$primaryKey, ?
                    FROM $table AS main_table
                    INNER JOIN $closureTable AS closure_table
                        ON main_table.$parentKey = closure_table.descendant_id
                    WHERE closure_table.depth = ?", [$depth, $depth - 1]);
                $continue = (bool) $connection->table($closureTable)->where('depth', '=', $depth)->exists();
                $depth++;
            }
        });

        $this->line("<info>Rebuilt the closures for:</info> $model");
    }
}
