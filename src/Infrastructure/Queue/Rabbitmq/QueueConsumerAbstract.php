<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq;

use Illuminate\Console\Command;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Log;

abstract class QueueConsumerAbstract extends Command
{
    protected $signature;

    protected string $queueName;

    protected string $routeKey;

    protected string $exchange = '';

    protected string $exchangeType = '';

    protected string $consumerTag = '';

    protected bool $isPassive = false;

    protected bool $isDurable = true;

    protected bool $isExclusive = false;

    protected bool $shouldAutoDelete = false;

    protected array $arguments = ['x-queue-type' => ['S', 'quorum']];

    /**
     * Quando true (padrão), garante antes do consumo que a DLQ referenciada
     * em x-dead-letter-routing-key exista no broker. Declarar a fila
     * principal com x-dead-letter-* NÃO cria a fila de destino: sem ela,
     * mensagens que estouram o x-delivery-limit (ou levam reject sem requeue)
     * são dead-letterizadas para uma routing key sem fila e DESCARTADAS em
     * silêncio pelo broker.
     *
     * PriorityQueueConsumerAbstract desliga esta flag porque a DLQ das filas
     * de prioridade já é garantida pela PriorityQueueTopology (que respeita
     * suffix/queue_type configuráveis por prioridade).
     */
    protected bool $ensureDeadLetterFromArguments = true;

    /**
     * Argumentos AMQP usados na declaração da DLQ derivada de
     * $this->arguments. Sobrescreva na subclasse se a DLQ não for quorum.
     */
    protected array $deadLetterQueueArguments = ['x-queue-type' => ['S', 'quorum']];

    public abstract function process($message): void;

    /**
     * @throws BindingResolutionException
     */
    public function handle(): void
    {
        try {
            $queueBuilder = app()->make(QueueBuilder::class);

            if ($this->ensureDeadLetterFromArguments) {
                $this->ensureDeadLetterQueueFromArguments($queueBuilder);
            }

            $queueConnector = $queueBuilder->setQueueName($this->queueName)
                ->setRouteKey($this->routeKey)
                ->setExchange($this->exchange)
                ->setExchangeType($this->exchangeType)
                ->setConsumerTag($this->consumerTag)
                ->setIsPassive($this->isPassive)
                ->setIsDurable($this->isDurable)
                ->setIsExclusive($this->isExclusive)
                ->setShouldAutoDelete($this->shouldAutoDelete)
                ->setArguments($this->arguments)
                ->getQueue();
            $queueConnector->consume([$this, 'process']);
            $queueConnector->destruct();
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            Log::error($th->getTraceAsString());
        }
    }

    /**
     * Declara a fila apontada por x-dead-letter-routing-key quando o
     * dead-letter usa o exchange default (''), caso em que a routing key É o
     * nome da fila de destino. Dead-letter via exchange nomeado não permite
     * derivar a fila e fica fora do escopo (no-op).
     *
     * A declaração usa conexão própria e é tolerante a divergência: se a fila
     * já existir com argumentos diferentes (PRECONDITION_FAILED), o objetivo
     * — existir — já está satisfeito; loga warning e segue sem interromper o
     * consumo da fila principal.
     */
    protected function ensureDeadLetterQueueFromArguments(QueueBuilder $queueBuilder): void
    {
        $deadLetterExchange = $this->arguments['x-dead-letter-exchange'][1] ?? null;
        $dlqName = $this->arguments['x-dead-letter-routing-key'][1] ?? null;

        if ($dlqName === null || $dlqName === '' || $deadLetterExchange !== '') {
            return;
        }

        try {
            $queue = $queueBuilder
                ->setQueueName($dlqName)
                ->setRouteKey($dlqName)
                ->setArguments($this->deadLetterQueueArguments)
                ->getQueue();

            $queue->queue_declare();
            $queue->destruct();
        } catch (\Throwable $th) {
            Log::warning('[' . class_basename(static::class) . '] Não foi possível garantir a DLQ (seguindo com o consumo).', [
                'dlq' => $dlqName,
                'error' => $th->getMessage(),
            ]);
        }
    }
}
