<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Services\RabbitMQ;

use PhpAmqpLib\Connection\AMQPStreamConnection;

abstract class RabbitMQ
{
    protected $connection;
    protected $channel;
    protected $exchangeType;
    protected $exchange;
    protected $queue;
    protected $routingKey;

    public function __construct($queue, $routingKey, $exchange, $exchangeType)
    {
        $this->queue = $queue;
        $this->exchange = $exchange;
        $this->routingKey = $routingKey;
        $this->exchangeType = $exchangeType;

        $this->connection = new AMQPStreamConnection(
            config('laravel-rabbitmq-worker.connections.host'),
            config('laravel-rabbitmq-worker.connections.port'),
            config('laravel-rabbitmq-worker.connections.user'),
            config('laravel-rabbitmq-worker.connections.password'),
            config('laravel-rabbitmq-worker.connections.vhost')
        );

        $this->channel = $this->connection->channel();

        if(!empty($this->exchange)) {
            $this->channel->exchange_declare($this->exchange, $this->exchangeType, false, true, false);
            $this->channel->queue_declare($this->queue, false, true, false, false);
            $this->channel->queue_bind($this->queue, $this->exchange, $this->routingKey);
        } else {
            $this->channel->queue_declare($this->queue, false, true, false, false);
        }
    }

    public function destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
