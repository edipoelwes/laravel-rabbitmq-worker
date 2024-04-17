<?php

namespace Edipoelwes\LaravelRabbitmqWorker;

use Edipoelwes\LaravelRabbitmqWorker\Commands\RunCommand;
use Illuminate\Support\ServiceProvider;

class CommandServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Registre os recursos aqui, se necessário
    }

    public function register()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RunCommand::class,
            ]);
        }
    }
}
