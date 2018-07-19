<?php

namespace Baril\Smoothie;

use Illuminate\Support\ServiceProvider;
use Baril\Smoothie\Console\FixPivotsCommand;
use Baril\Smoothie\Console\FixPositionsCommand;
use Baril\Smoothie\Console\FixTreeCommand;
use Baril\Smoothie\Console\GrowTreeCommand;

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
    }
}
