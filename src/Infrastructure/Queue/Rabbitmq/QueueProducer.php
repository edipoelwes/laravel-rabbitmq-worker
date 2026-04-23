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
        $serializedPayload = json_encode($payload);
        $rabbitmqConnector = $this->queueBuilder->setQueueName($queueName)
            ->setRouteKey($queueName)
            ->setArguments($arguments)
            ->getQueue();
        $rabbitmqConnector->publish($serializedPayload);
        $rabbitmqConnector->destruct();
    }

    public function produceBatch(string $queueName, array $payload, array $arguments = []): void
    {
        $rabbitmqConnector = $this->queueBuilder->setQueueName($queueName)
            ->setRouteKey($queueName)
            ->setArguments($arguments)
            ->getQueue();
        $rabbitmqConnector->publishBatch($payload);
        $rabbitmqConnector->destruct();
    }
}
