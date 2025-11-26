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
                $process = new Process(['php', 'artisan', $worker]);
                $process->setTimeout(null);
                $process->start();

                $processes[] = [
                    'worker' => $worker,
                    'process' => $process
                ];

                $this->info("Started worker: {$worker}");
            }

            // Monitor all processes and output their logs
            while (count($processes) > 0) {
                foreach ($processes as $key => $item) {
                    $process = $item['process'];
                    $worker = $item['worker'];

                    // Output stdout
                    if ($output = $process->getIncrementalOutput()) {
                        $this->line("[{$worker}] {$output}");
                    }

                    // Output stderr
                    if ($errorOutput = $process->getIncrementalErrorOutput()) {
                        $this->error("[{$worker}] {$errorOutput}");
                    }

                    // Remove finished processes
                    if (!$process->isRunning()) {
                        $exitCode = $process->getExitCode();
                        $this->warn("Worker {$worker} stopped with exit code: {$exitCode}");
                        unset($processes[$key]);
                    }
                }

                // Small sleep to avoid high CPU usage
                usleep(100000); // 100ms
            }

            $this->warn('All workers have stopped');
        } catch (\Throwable $th) {
            Log::error(__METHOD__.' '.__LINE__, ['context' => $th->getMessage()]);
            $this->error('Error: ' . $th->getMessage());
        }
    }
}
