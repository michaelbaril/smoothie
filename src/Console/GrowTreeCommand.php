<?php

namespace Baril\Smoothie\Console;

use Illuminate\Database\Console\Migrations\MigrateMakeCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GrowTreeCommand extends MigrateMakeCommand
{
    protected $signature = 'smoothie:grow-tree {model : The model class.}
        {--name= : The name of the migration.}
        {--path= : The location where the migration file should be created.}
        {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths.}
        {--migrate : Migrate the database and fill the table after the migration file has been created.}';
    protected $description = 'Create the migration file for a closure table, and optionally run the migration';

    public function handle()
    {
        $model = $this->input->getArgument('model');
        if (!class_exists($model) || !is_subclass_of($model, Model::class) || !method_exists($model, 'getClosureTable')) {
            $this->error('{model} must be a valid model class and use the BelongsToTree trait!');
            return;
        }

        $this->writeClosureMigration($model);
        $this->composer->dumpAutoloads();

        if ($this->input->hasOption('migrate') && $this->option('migrate')) {
            $this->call('migrate');
            $this->call('smoothie:fix-tree', ['model' => $model]);
        }
    }

    protected function writeClosureMigration($model)
    {
        // Retrieve all informations about the tree:
        $instance = new $model;
        $table = $instance->getTable();
        $closureTable = $instance->getClosureTable();
        $keyName = $instance->getKeyName();

        // Get the name for the migration file:
        $name = $this->input->getOption('name') ?: 'create_' . $closureTable . '_table';
        $name = Str::snake(trim($name));
        $className = Str::studly($name);

        // Generate the content of the migration file:
        $contents = $this->getMigrationContents($className, $table, $closureTable, $keyName);

        // Generate the file:
        $file = $this->creator->create(
            $name, $this->getMigrationPath(), $closureTable, true
        );
        file_put_contents($file, $contents);

        // Output information:
        $file = pathinfo($file, PATHINFO_FILENAME);
        $this->line("<info>Created Migration:</info> {$file}");
    }

    protected function getMigrationContents($className, $table, $closureTable, $keyName)
    {
        $contents = file_get_contents(__DIR__ . '/stubs/grow_tree.stub');
        $contents = str_replace([
            'class CreateExampleTreeTable',
            '$mainTableName = "example"',
            '$closureTableName = "example_tree"',
            '$mainTableKey = "id"',
        ], [
            'class ' . $className,
            '$mainTableName = "' . $table . '"',
            '$closureTableName = "' . $closureTable . '"',
            '$mainTableKey = "' . $keyName . '"',
        ], $contents);
        $contents = preg_replace('/\;[\s]*\/\/.*\n/U', ";\n", $contents);
        return $contents;
    }
}
