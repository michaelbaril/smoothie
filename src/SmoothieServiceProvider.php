<?php

namespace Baril\Smoothie;

use Baril\Smoothie\Console\FixPivotsCommand;
use Baril\Smoothie\Console\FixPositionsCommand;
use Baril\Smoothie\Console\FixTreeCommand;
use Baril\Smoothie\Console\GrowTreeCommand;
use Baril\Smoothie\Console\ShowTreeCommand;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\ServiceProvider;

class SmoothieServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands([
            FixPivotsCommand::class,
            FixPositionsCommand::class,
            FixTreeCommand::class,
            GrowTreeCommand::class,
            ShowTreeCommand::class,
        ]);

        Builder::macro('debugSql', function() {
            $bindings = array_map(function ($value) {
                return is_string($value) ? '"'.addcslashes($value, '"').'"' : $value;
            }, $this->getBindings());
            return vsprintf(str_replace('?', '%s', $this->toSql()), $bindings);
        });

        EloquentBuilder::macro('debugSql', function() {
            $bindings = array_map(function ($value) {
                return is_string($value) ? '"'.addcslashes($value, '"').'"' : $value;
            }, $this->getBindings());
            return vsprintf(str_replace('?', '%s', $this->toSql()), $bindings);
        });

        EloquentBuilder::macro('findInOrder', function ($ids, $columns = ['*']) {
            return $this->findMany($ids, $columns)->sortByKeys($ids);
        });

        Collection::macro('sortByKeys', function(array $ids) {
            $ids = array_flip(array_values($ids));
            $i = $this->count();
            return $this->sortBy(function ($model) use ($ids, &$i) {
                return $ids[$model->getKey()] ?? ++$i;
            });
        });

        Relation::macro('crossDatabase', function ($pivot = null) {
            $relatedModel = $this->getRelated();
            $database = $relatedModel->getConnection()->getDatabaseName();
            $table = $relatedModel->getTable();

            $relatedModel->setTable("$database.$table");

            if ($this instanceof BelongsToMany) {
                if ($pivot == 'related') {
                    $pivotTable = "$database.{$this->table}";
                } elseif ($pivot == 'parent') {
                    $parentDatabase = $this->getParent()->getConnection()->getDatabaseName();
                    $pivotTable = "$parentDatabase.{$this->table}";
                }
                foreach ($this->query->getQuery()->joins as $join) {
                    if ($join->table == $this->table) {
                        $join->table = $pivotTable;
                    }
                }
                $this->table = $pivotTable;
            }

            return $this;
        });
    }
}
