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
}
