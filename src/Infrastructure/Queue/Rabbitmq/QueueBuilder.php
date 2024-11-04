<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq;

use Edipoelwes\LaravelRabbitmqWorker\Services\RabbitMQ\RabbitMQService;


class QueueBuilder
{
    protected string $queueName;

    protected string $routeKey;

    protected string $exchange = 'direct';

    protected string $exchangeType = 'direct';

    protected string $consumerTag = '';

    protected bool $isPassive = false;

    protected bool $isDurable = true;

    protected bool $isExclusive = false;

    protected bool $shouldAutoDelete = false;

    /**
     * @param string $queueName
     * @return QueueBuilder
     */
    public function setQueueName(string $queueName): QueueBuilder
    {
        $this->queueName = $queueName;
        return $this;
    }

    /**
     * @param string $routeKey
     * @return QueueBuilder
     */
    public function setRouteKey(string $routeKey): QueueBuilder
    {
        $this->routeKey = $routeKey;
        return $this;
    }

    /**
     * @param string $exchange
     * @return QueueBuilder
     */
    public function setExchange(string $exchange): QueueBuilder
    {
        $this->exchange = $exchange;
        return $this;
    }

    /**
     * @param string $exchangeType
     * @return QueueBuilder
     */
    public function setExchangeType(string $exchangeType): QueueBuilder
    {
        $this->exchangeType = $exchangeType;
        return $this;
    }

    /**
     * @param string $consumerTag
     * @return QueueBuilder
     */
    public function setConsumerTag(string $consumerTag): QueueBuilder
    {
        $this->consumerTag = $consumerTag;
        return $this;
    }

    /**
     * @param bool $isPassive
     * @return QueueBuilder
     */
    public function setIsPassive(bool $isPassive): QueueBuilder
    {
        $this->isPassive = $isPassive;
        return $this;
    }

    /**
     * @param bool $isDurable
     * @return QueueBuilder
     */
    public function setIsDurable(bool $isDurable): QueueBuilder
    {
        $this->isDurable = $isDurable;
        return $this;
    }

    /**
     * @param bool $isExclusive
     * @return QueueBuilder
     */
    public function setIsExclusive(bool $isExclusive): QueueBuilder
    {
        $this->isExclusive = $isExclusive;
        return $this;
    }

    /**
     * @param bool $shouldAutoDelete
     * @return QueueBuilder
     */
    public function setShouldAutoDelete(bool $shouldAutoDelete): QueueBuilder
    {
        $this->shouldAutoDelete = $shouldAutoDelete;
        return $this;
    }

    public function getQueue(): RabbitMQService
    {
        return new RabbitMQService(
            $this->queueName,
            $this->routeKey,
            $this->exchange,
            $this->exchangeType,
            $this->consumerTag,
            $this->isPassive,
            $this->isDurable,
            $this->isExclusive,
            $this->shouldAutoDelete
        );
    }
}
