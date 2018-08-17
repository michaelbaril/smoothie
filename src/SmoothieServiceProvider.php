<?php

namespace Baril\Smoothie;

use Baril\Smoothie\Console\FixPivotsCommand;
use Baril\Smoothie\Console\FixPositionsCommand;
use Baril\Smoothie\Console\FixTreeCommand;
use Baril\Smoothie\Console\GrowTreeCommand;
use Illuminate\Database\Eloquent\Collection;
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
        ]);

        Collection::macro('sortByKeys', function(array $ids) {
            $ids = array_flip(array_values($ids));
            $i = $this->count();
            return $this->sortBy(function ($model) use ($ids, &$i) {
                return $ids[$model->getKey()] ?? ++$i;
            });
        });
    }
}
