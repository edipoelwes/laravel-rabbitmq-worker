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
 *
 * Namespaces remotos: além da topologia local (priority.queues), a lib
 * suporta publicar nas filas de prioridade de OUTRO sistema declarado em
 * priority.remotes.<nome> (ex.: o Eco UTM publicando em dasa_priority_high,
 * cujo consumer vive no DASA). Todos os resolvedores aceitam um $remote
 * opcional: null resolve na topologia local; um nome resolve no bloco
 * remoto correspondente. O CONSUMO é sempre local — remoto é só publicação.
 */
class PriorityQueueTopology
{
    private const VALID_PRIORITIES = ['high', 'default', 'low'];

    public function queueName(string $priority, ?string $remote = null): string
    {
        $queueName = config($this->configPath('queues.' . $priority, $remote));

        if (!$queueName) {
            $scope = $remote ? "priority.remotes.{$remote}.queues" : 'priority.queues';
            throw new \InvalidArgumentException("Prioridade RabbitMQ não mapeada em {$scope}: {$priority}");
        }

        return $queueName;
    }

    /**
     * Rota configurada em priority.routes para o message_type, ou null se
     * não houver nenhuma.
     */
    public function route(string $messageType): ?array
    {
        $route = config("laravel-rabbitmq-worker.priority.routes.{$messageType}");

        return is_array($route) ? $route : null;
    }

    /**
     * Resolve a prioridade configurada em priority.routes.<message_type>.priority.
     *
     * @param string|null $fallback Usado quando não há rota (ou a rota não define
     *                              `priority`). Passe null para lançar exceção
     *                              nesse caso em vez de cair num valor padrão.
     *
     * @throws \InvalidArgumentException quando não há rota e $fallback é null,
     *                                    ou quando a prioridade configurada não é
     *                                    high|default|low.
     */
    public function priorityForMessageType(string $messageType, ?string $fallback = 'default'): string
    {
        $route = $this->route($messageType);
        $priority = $route['priority'] ?? null;

        if ($priority === null) {
            if ($fallback === null) {
                throw new \InvalidArgumentException(
                    "message_type '{$messageType}' não está mapeado em priority.routes (ou não define 'priority')."
                );
            }

            $priority = $fallback;
        }

        return $this->assertValidPriority($priority, $messageType);
    }

    /**
     * Resolve o namespace remoto configurado em
     * priority.routes.<message_type>.remote, ou null quando a rota publica na
     * topologia local (comportamento padrão).
     *
     * @throws \InvalidArgumentException quando a rota aponta para um remote
     *                                    não declarado em priority.remotes.
     */
    public function remoteForMessageType(string $messageType): ?string
    {
        $remote = $this->route($messageType)['remote'] ?? null;

        if ($remote === null) {
            return null;
        }

        if (!is_array(config("laravel-rabbitmq-worker.priority.remotes.{$remote}"))) {
            throw new \InvalidArgumentException(
                "message_type '{$messageType}' aponta para o remote '{$remote}', "
                . "mas ele não está declarado em priority.remotes."
            );
        }

        return $remote;
    }

    private function assertValidPriority(string $priority, string $messageType): string
    {
        if (!in_array($priority, self::VALID_PRIORITIES, true)) {
            throw new \InvalidArgumentException(
                "Prioridade '{$priority}' configurada para message_type '{$messageType}' é inválida. "
                . 'Valores aceitos: ' . implode(', ', self::VALID_PRIORITIES) . '.'
            );
        }

        return $priority;
    }

    public function deadLetterEnabled(string $priority, ?string $remote = null): bool
    {
        return (bool) $this->dlqSetting($priority, 'enabled', true, $remote);
    }

    public function deadLetterQueueName(string $priority, ?string $remote = null): string
    {
        return $this->queueName($priority, $remote) . $this->dlqSetting($priority, 'suffix', '.dlq', $remote);
    }

    public function deliveryLimit(string $priority, ?string $remote = null): int
    {
        return (int) $this->dlqSetting($priority, 'delivery_limit', 3, $remote);
    }

    public function deadLetterQueueType(string $priority, ?string $remote = null): string
    {
        return (string) $this->dlqSetting($priority, 'queue_type', 'quorum', $remote);
    }

    /**
     * Argumentos a mesclar na fila principal de prioridade para rotear
     * mensagens rejeitadas/esgotadas para a DLQ correspondente. Retorna
     * array vazio quando a DLQ está desabilitada para a prioridade.
     */
    public function mainQueueDeadLetterArguments(string $priority, ?string $remote = null): array
    {
        if (!$this->deadLetterEnabled($priority, $remote)) {
            return [];
        }

        return [
            'x-dead-letter-exchange' => ['S', ''],
            'x-dead-letter-routing-key' => ['S', $this->deadLetterQueueName($priority, $remote)],
            'x-delivery-limit' => ['I', $this->deliveryLimit($priority, $remote)],
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
     * está desabilitada para a prioridade. Sempre local: DLQ de namespace
     * remoto é responsabilidade do sistema dono das filas.
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
    private function dlqSetting(string $priority, string $key, $default, ?string $remote = null)
    {
        $perPriority = config($this->configPath("dead_letter.priorities.{$priority}.{$key}", $remote));

        if ($perPriority !== null) {
            return $perPriority;
        }

        return config($this->configPath("dead_letter.{$key}", $remote), $default);
    }

    /**
     * Caminho de config do bloco de prioridade: local (priority.*) ou de um
     * namespace remoto (priority.remotes.<nome>.*).
     */
    private function configPath(string $suffix, ?string $remote = null): string
    {
        $base = $remote === null
            ? 'laravel-rabbitmq-worker.priority.'
            : "laravel-rabbitmq-worker.priority.remotes.{$remote}.";

        return $base . $suffix;
    }
}
