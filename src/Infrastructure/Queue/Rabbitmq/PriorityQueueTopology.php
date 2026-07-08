<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq;

/**
 * Fonte única de verdade para nomes e argumentos AMQP das filas de
 * prioridade (high/default/low) e de suas DLQs. Centraliza aqui o que antes
 * ficaria espalhado entre QueueProducer e PriorityQueueConsumerAbstract,
 * para que ambos declarem exatamente os mesmos argumentos e evitem
 * PRECONDITION_FAILED.
 *
 * A DLQ é por fila física de prioridade (ex.: priority_high -> priority_high.dlq),
 * não por message_type: um app usando a lib só precisa dessas 3 filas + 3 DLQs,
 * independente de quantos message_type existam em priority.routes.
 */
class PriorityQueueTopology
{
    public function queueName(string $priority): string
    {
        $queueName = config("laravel-rabbitmq-worker.priority.queues.{$priority}");

        if (!$queueName) {
            throw new \InvalidArgumentException("Prioridade RabbitMQ não mapeada em priority.queues: {$priority}");
        }

        return $queueName;
    }

    public function deadLetterEnabled(string $priority): bool
    {
        return (bool) $this->dlqSetting($priority, 'enabled', true);
    }

    public function deadLetterQueueName(string $priority): string
    {
        return $this->queueName($priority) . $this->dlqSetting($priority, 'suffix', '.dlq');
    }

    public function deliveryLimit(string $priority): int
    {
        return (int) $this->dlqSetting($priority, 'delivery_limit', 3);
    }

    public function deadLetterQueueType(string $priority): string
    {
        return (string) $this->dlqSetting($priority, 'queue_type', 'quorum');
    }

    /**
     * Argumentos a mesclar na fila principal de prioridade para rotear
     * mensagens rejeitadas/esgotadas para a DLQ correspondente. Retorna
     * array vazio quando a DLQ está desabilitada para a prioridade.
     */
    public function mainQueueDeadLetterArguments(string $priority): array
    {
        if (!$this->deadLetterEnabled($priority)) {
            return [];
        }

        return [
            'x-dead-letter-exchange' => ['S', ''],
            'x-dead-letter-routing-key' => ['S', $this->deadLetterQueueName($priority)],
            'x-delivery-limit' => ['I', $this->deliveryLimit($priority)],
        ];
    }

    /**
     * Argumentos AMQP da própria fila .dlq.
     */
    public function deadLetterQueueArguments(string $priority): array
    {
        return [
            'x-queue-type' => ['S', $this->deadLetterQueueType($priority)],
        ];
    }

    /**
     * Garante que a DLQ da prioridade exista no broker. No-op quando a DLQ
     * está desabilitada para a prioridade.
     */
    public function ensureDeadLetterQueue(string $priority, QueueBuilder $queueBuilder): void
    {
        if (!$this->deadLetterEnabled($priority)) {
            return;
        }

        $dlqName = $this->deadLetterQueueName($priority);

        $queue = $queueBuilder
            ->setQueueName($dlqName)
            ->setRouteKey($dlqName)
            ->setArguments($this->deadLetterQueueArguments($priority))
            ->getQueue();

        $queue->queue_declare();
        $queue->destruct();
    }

    /**
     * @return mixed
     */
    private function dlqSetting(string $priority, string $key, $default)
    {
        $perPriority = config("laravel-rabbitmq-worker.priority.dead_letter.priorities.{$priority}.{$key}");

        if ($perPriority !== null) {
            return $perPriority;
        }

        return config("laravel-rabbitmq-worker.priority.dead_letter.{$key}", $default);
    }
}
