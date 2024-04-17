<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Commands;

use Illuminate\Console\Command;

class RunCommand extends Command
{
    protected $signature = 'hello:world';

    protected $description = 'Pequeno comando para demonstração sobre a criação de comandos no laravel';

    public function handle()
    {
        return $this->info('Olá Mundo dos pacotes no Laravel');
    }
}
