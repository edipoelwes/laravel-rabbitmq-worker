<?php

namespace Edipoelwes\LaravelRabbitmqWorker;

use Edipoelwes\LaravelRabbitmqWorker\Commands\RunCommand;
use Illuminate\Support\ServiceProvider;

class CommandServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/laravel-rabbitmq-worker.php' => config_path('laravel-rabbitmq-worker.php'),
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom( __DIR__ . '/../config/laravel-rabbitmq-worker.php', 'laravel-rabbitmq-worker');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RunCommand::class,
            ]);
        }
    }
}
