<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq;

class QueueProducer
{

    private QueueBuilder $queueBuilder;

    public function __construct(
        QueueBuilder $queueBuilder
    ) {
        $this->queueBuilder = $queueBuilder;
    }

    public function produce(string $queueName, array $payload, array $arguments = []): void
    {
        $this->produceWithHeaders($queueName, $payload, [], $arguments);
    }

    public function produceWithHeaders(string $queueName, array $payload, array $headers = [], array $arguments = []): void
    {
        $serializedPayload = json_encode($payload);
        $rabbitmqConnector = $this->queueBuilder->setQueueName($queueName)
            ->setRouteKey($queueName)
            ->setArguments($arguments)
            ->getQueue();
        $rabbitmqConnector->publishWithHeaders($serializedPayload, $headers);
        $rabbitmqConnector->destruct();
    }

    public function produceBatch(string $queueName, array $payload, array $arguments = []): void
    {
        $this->produceBatchWithHeaders($queueName, $payload, [], $arguments);
    }

    public function produceBatchWithHeaders(string $queueName, array $payloads, array $headers = [], array $arguments = []): void
    {
        $rabbitmqConnector = $this->queueBuilder->setQueueName($queueName)
            ->setRouteKey($queueName)
            ->setArguments($arguments)
            ->getQueue();
        $rabbitmqConnector->publishBatchWithHeaders($payloads, $headers);
        $rabbitmqConnector->destruct();
    }

    /**
     * Publica um payload único na fila física da prioridade informada, marcando
     * o tipo funcional da mensagem via header `message_type` para o
     * PriorityMessageRouter delegar ao consumer correto.
     *
     * @param string|null $priority 'high'|'default'|'low'. Nulo cai em 'default'.
     */
    public function producePriority(?string $priority, string $messageType, array $payload, array $arguments = []): void
    {
        $this->produceWithHeaders(
            $this->resolvePriorityQueue($priority),
            $payload,
            ['message_type' => $messageType],
            $arguments
        );
    }

    /**
     * Versão em lote de producePriority().
     *
     * @param string|null $priority 'high'|'default'|'low'. Nulo cai em 'default'.
     */
    public function producePriorityBatch(?string $priority, string $messageType, array $payloads, array $arguments = []): void
    {
        $this->produceBatchWithHeaders(
            $this->resolvePriorityQueue($priority),
            $payloads,
            ['message_type' => $messageType],
            $arguments
        );
    }

    private function resolvePriorityQueue(?string $priority): string
    {
        $priority = $priority ?: 'default';
        $queueName = config("laravel-rabbitmq-worker.priority.queues.{$priority}");

        if (!$queueName) {
            throw new \InvalidArgumentException("Prioridade RabbitMQ não mapeada: {$priority}");
        }

        return $queueName;
    }
}
