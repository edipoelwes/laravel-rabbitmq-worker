<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Commands;

use Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq\PriorityQueueConsumerAbstract;

/**
 * Worker da fila de prioridade `default`. Registrado no Artisan como
 * "<command_prefix>_priority_default" (config laravel-rabbitmq-worker.command_prefix)
 * e consome a fila física de config('laravel-rabbitmq-worker.priority.queues.default').
 */
class PriorityDefaultConsumerCommand extends PriorityQueueConsumerAbstract
{
    protected string $priority = 'default';

    protected $description = 'Consome a fila RabbitMQ de prioridade default';
}
