<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class RunCommand extends Command
{
    protected $signature = 'rabbitmq:run {workers}';

    protected $description = 'Runs processes in the background';

    public function handle()
    {
        try {
            $workers = explode(',', $this->argument('workers'));
            $processes = [];

            foreach ($workers as $worker) {
                $process = new Process('php artisan worker:'.$worker);
                $process->setTimeout(null);
                $process->start();

                $processes[] = $process;
            }

            foreach ($processes as $process) {
                $process->wait();
            }
        } catch (\Throwable $th) {
            Log::error(__METHOD__.' '.__LINE__, ['context' => $th->getMessage()]);
        }
    }
}
