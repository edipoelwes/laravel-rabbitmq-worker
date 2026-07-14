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
     * @param string|null $remote   Namespace remoto de priority.remotes para
     *                              publicar nas filas de prioridade de outro
     *                              sistema. Nulo publica na topologia local.
     */
    public function producePriority(?string $priority, string $messageType, array $payload, array $arguments = [], ?string $remote = null): void
    {
        $priority = $priority ?: 'default';

        $this->produceWithHeaders(
            $this->topology->queueName($priority, $remote),
            $payload,
            ['message_type' => $messageType],
            $this->priorityArguments($priority, $arguments, $remote)
        );
    }

    /**
     * Versão em lote de producePriority().
     *
     * @param string|null $priority 'high'|'default'|'low'. Nulo cai em 'default'.
     * @param string|null $remote   Namespace remoto de priority.remotes. Nulo
     *                              publica na topologia local.
     */
    public function producePriorityBatch(?string $priority, string $messageType, array $payloads, array $arguments = [], ?string $remote = null): void
    {
        $priority = $priority ?: 'default';

        $this->produceBatchWithHeaders(
            $this->topology->queueName($priority, $remote),
            $payloads,
            ['message_type' => $messageType],
            $this->priorityArguments($priority, $arguments, $remote)
        );
    }

    /**
     * Publica um payload único inferindo prioridade e destino a partir de
     * config('laravel-rabbitmq-worker.priority.routes.<message_type>'):
     * `priority` define a fila high/default/low e o opcional `remote` publica
     * no namespace de outro sistema (priority.remotes.<remote>), eliminando a
     * necessidade de repetir 'high'|'default'|'low' ou nomes de fila no app.
     *
     * @throws \InvalidArgumentException quando o message_type não está mapeado
     *                                    em priority.routes (ou a rota não define
     *                                    'priority'), ou aponta para um remote
     *                                    não declarado em priority.remotes.
     */
    public function produceRouted(string $messageType, array $payload, array $arguments = []): void
    {
        $this->producePriority(
            $this->topology->priorityForMessageType($messageType, null),
            $messageType,
            $payload,
            $arguments,
            $this->topology->remoteForMessageType($messageType)
        );
    }

    /**
     * Versão em lote de produceRouted().
     *
     * @throws \InvalidArgumentException quando o message_type não está mapeado
     *                                    em priority.routes (ou a rota não define
     *                                    'priority'), ou aponta para um remote
     *                                    não declarado em priority.remotes.
     */
    public function produceRoutedBatch(string $messageType, array $payloads, array $arguments = []): void
    {
        $this->producePriorityBatch(
            $this->topology->priorityForMessageType($messageType, null),
            $messageType,
            $payloads,
            $arguments,
            $this->topology->remoteForMessageType($messageType)
        );
    }

    private function priorityArguments(string $priority, array $arguments, ?string $remote = null): array
    {
        return array_merge($arguments, $this->topology->mainQueueDeadLetterArguments($priority, $remote));
    }
}
