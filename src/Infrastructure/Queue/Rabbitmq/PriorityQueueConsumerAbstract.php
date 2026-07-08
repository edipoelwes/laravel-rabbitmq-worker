<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq;

use Illuminate\Support\Facades\Log;

/**
 * Consumer compartilhado de uma fila de prioridade (high/default/low).
 * Resolve a fila física em config('laravel-rabbitmq-worker.priority.queues')
 * a partir de $priority e delega cada mensagem ao PriorityMessageRouter.
 *
 * A aplicação só precisa de uma subclasse fina por prioridade ativa:
 *
 *     class PriorityDefaultCommand extends PriorityQueueConsumerAbstract
 *     {
 *         protected $signature = 'minha_fila_default';
 *         protected string $priority = 'default';
 *     }
 */
abstract class PriorityQueueConsumerAbstract extends QueueConsumerAbstract
{
    /** 'high'|'default'|'low' */
    protected string $priority = 'default';

    public function handle(): void
    {
        $queueName = config("laravel-rabbitmq-worker.priority.queues.{$this->priority}");

        if (!$queueName) {
            $this->error("Prioridade RabbitMQ não mapeada em priority.queues: {$this->priority}");
            return;
        }

        $this->queueName = $queueName;
        $this->routeKey = $queueName;
        $this->consumerTag = $queueName;

        parent::handle();
    }

    public function process($message): void
    {
        try {
            app(PriorityMessageRouter::class)->dispatch($message);
        } catch (\Throwable $e) {
            Log::error('[' . class_basename(static::class) . '] Falha ao rotear mensagem.', [
                'error' => $e->getMessage(),
                'body' => $message->body,
            ]);
            $message->reject(false);
        }
    }
}
