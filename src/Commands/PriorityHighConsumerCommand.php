<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Commands;

use Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq\PriorityQueueConsumerAbstract;

/**
 * Worker da fila de prioridade `high`. Registrado no Artisan como
 * "<command_prefix>_priority_high" (config laravel-rabbitmq-worker.command_prefix)
 * e consome a fila física de config('laravel-rabbitmq-worker.priority.queues.high').
 */
class PriorityHighConsumerCommand extends PriorityQueueConsumerAbstract
{
    protected string $priority = 'high';

    protected $description = 'Consome a fila RabbitMQ de prioridade high';
}
