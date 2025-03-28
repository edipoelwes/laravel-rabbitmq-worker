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

    public function produce(string $queueName, array $payload): void
    {
        $serializedPayload = json_encode($payload);
        $rabbitmqConnector = $this->queueBuilder->setQueueName($queueName)
            ->setRouteKey($queueName)->getQueue();
        $rabbitmqConnector->publish($serializedPayload);
        $rabbitmqConnector->destruct();
    }

    public function produceBatch(string $queueName, array $payload): void
    {
        $rabbitmqConnector = $this->queueBuilder->setQueueName($queueName)
            ->setRouteKey($queueName)->getQueue();
        $rabbitmqConnector->publishBatch($payload);
        $rabbitmqConnector->destruct();
    }
}
