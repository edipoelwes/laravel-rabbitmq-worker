<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Commands;

use Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq\PriorityQueueConsumerAbstract;

/**
 * Worker da fila de prioridade `low`. Registrado no Artisan como
 * "<command_prefix>_priority_low" (config laravel-rabbitmq-worker.command_prefix)
 * e consome a fila física de config('laravel-rabbitmq-worker.priority.queues.low').
 */
class PriorityLowConsumerCommand extends PriorityQueueConsumerAbstract
{
    protected string $priority = 'low';

    protected $description = 'Consome a fila RabbitMQ de prioridade low';
}
