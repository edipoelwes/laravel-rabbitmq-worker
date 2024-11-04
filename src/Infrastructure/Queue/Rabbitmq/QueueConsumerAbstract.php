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

    public abstract function process($message): void;

    /**
     * @throws BindingResolutionException
     */
    public function handle(): void
    {
        try {
            $queueBuilder = app()->make(QueueBuilder::class);
            $queueConnector = $queueBuilder->setQueueName($this->queueName)
                ->setRouteKey($this->routeKey)
                ->setExchange($this->exchange)
                ->setExchangeType($this->exchangeType)
                ->setConsumerTag($this->consumerTag)
                ->setIsPassive($this->isPassive)
                ->setIsDurable($this->isDurable)
                ->setIsExclusive($this->isExclusive)
                ->setShouldAutoDelete($this->shouldAutoDelete)
                ->getQueue();
            $queueConnector->consume([$this, 'process']);
            $queueConnector->destruct();
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            Log::error($th->getTraceAsString());
        }
    }
}
