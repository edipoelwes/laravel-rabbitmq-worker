<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq;

use Illuminate\Support\Facades\Log;

/**
 * Consumer compartilhado de uma fila de prioridade (high/default/low).
 * Resolve a fila física em config('laravel-rabbitmq-worker.priority.queues')
 * a partir de $priority e delega cada mensagem ao PriorityMessageRouter.
 *
 * A lib já fornece um command concreto por prioridade (ver
 * Commands\PriorityHighConsumerCommand etc.), nomeado como
 * "<command_prefix>_priority_<priority>". Uma subclasse só precisa definir
 * $signature se quiser um nome fora desse padrão:
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

    public function __construct()
    {
        if (empty($this->signature)) {
            $prefix = config('laravel-rabbitmq-worker.command_prefix', 'rabbitmq');
            $this->signature = "{$prefix}_priority_{$this->priority}";
        }

        parent::__construct();
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function handle(): void
    {
        $topology = app(PriorityQueueTopology::class);

        try {
            $queueName = $topology->queueName($this->priority);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return;
        }

        $this->queueName = $queueName;
        $this->routeKey = $queueName;
        $this->consumerTag = $queueName;
        $this->arguments = array_merge($this->arguments, $topology->mainQueueDeadLetterArguments($this->priority));

        $topology->ensureDeadLetterQueue($this->priority, app(QueueBuilder::class));

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
