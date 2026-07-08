<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq;

class QueueProducer
{

    private QueueBuilder $queueBuilder;

    private PriorityQueueTopology $topology;

    public function __construct(
        QueueBuilder $queueBuilder,
        PriorityQueueTopology $topology
    ) {
        $this->queueBuilder = $queueBuilder;
        $this->topology = $topology;
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
     * Os argumentos AMQP da fila (DLQ/delivery-limit) são resolvidos
     * automaticamente pela mesma topologia usada pelo consumer, para evitar
     * PRECONDITION_FAILED por divergência de argumentos.
     *
     * @param string|null $priority 'high'|'default'|'low'. Nulo cai em 'default'.
     */
    public function producePriority(?string $priority, string $messageType, array $payload, array $arguments = []): void
    {
        $priority = $priority ?: 'default';

        $this->produceWithHeaders(
            $this->topology->queueName($priority),
            $payload,
            ['message_type' => $messageType],
            $this->priorityArguments($priority, $arguments)
        );
    }

    /**
     * Versão em lote de producePriority().
     *
     * @param string|null $priority 'high'|'default'|'low'. Nulo cai em 'default'.
     */
    public function producePriorityBatch(?string $priority, string $messageType, array $payloads, array $arguments = []): void
    {
        $priority = $priority ?: 'default';

        $this->produceBatchWithHeaders(
            $this->topology->queueName($priority),
            $payloads,
            ['message_type' => $messageType],
            $this->priorityArguments($priority, $arguments)
        );
    }

    private function priorityArguments(string $priority, array $arguments): array
    {
        return array_merge($arguments, $this->topology->mainQueueDeadLetterArguments($priority));
    }
}
