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
            $primaryKey = $instance->getQualifiedKeyName();
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
                    SELECT $closureTable.ancestor_id, $primaryKey, ?
                    FROM $table
                    INNER JOIN $closureTable
                        ON $table.$parentKey = $closureTable.descendant_id
                    WHERE $closureTable.depth = ?", [$depth, $depth - 1]);
                $continue = (bool) $connection->table($closureTable)->where('depth', '=', $depth)->exists();
                $depth++;
            }
        });

        $this->line("<info>Rebuilt the closures for:</info> $model");
    }
}
