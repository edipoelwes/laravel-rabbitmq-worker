<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Services\RabbitMQ;

use Illuminate\Support\Str;
use PhpAmqpLib\Connection\AMQPStreamConnection;

abstract class RabbitMQ
{
    protected $connection;
    protected $channel;
    protected $exchangeType;
    protected $exchange;
    protected $queue;
    protected $routingKey;
    protected $consumerTag;
    protected $passive;
    protected $durable;
    protected $exclusive;
    protected $autoDelete;
    protected $response = null;
    protected $correlation_id;
    protected $nowait = false;
    protected $arguments = ['x-queue-type' => ['S', 'quorum']];

    public function __construct(
        $queue,
        $routingKey,
        $exchange,
        $exchangeType,
        $consumerTag,
        $passive,
        $durable,
        $exclusive,
        $autoDelete
    )
    {
        $this->queue = $queue;
        $this->exchange = $exchange;
        $this->routingKey = $routingKey;
        $this->exchangeType = $exchangeType;
        $this->consumerTag = is_null($consumerTag) ? '' : 'amq.tag.'.$consumerTag;
        $this->passive = $passive;
        $this->exclusive = $exclusive;
        $this->durable = $durable;
        $this->autoDelete = $autoDelete;
        $this->correlation_id = Str::uuid();;

        $this->connection = new AMQPStreamConnection(
            config('laravel-rabbitmq-worker.connections.host'),
            config('laravel-rabbitmq-worker.connections.port'),
            config('laravel-rabbitmq-worker.connections.user'),
            config('laravel-rabbitmq-worker.connections.password'),
            config('laravel-rabbitmq-worker.connections.vhost'),
            config('laravel-rabbitmq-worker.connections.insist'),
            config('laravel-rabbitmq-worker.connections.login_method'),
            config('laravel-rabbitmq-worker.connections.login_response'),
            config('laravel-rabbitmq-worker.connections.locale'),
            config('laravel-rabbitmq-worker.connections.connection_timeout'),
            config('laravel-rabbitmq-worker.connections.read_write_timeout'),
            config('laravel-rabbitmq-worker.connections.context'),
            config('laravel-rabbitmq-worker.connections.heartbeat'),
            config('laravel-rabbitmq-worker.connections.channel_rpc_timeout'),
            config('laravel-rabbitmq-worker.connections.ssl_protocol'),
        );

        $this->channel = $this->connection->channel();
    }

    public function queue_declare()
    {
        if(!empty($this->exchange)) {
            $this->channel->exchange_declare($this->exchange, $this->exchangeType, $this->passive, $this->durable , $this->autoDelete);
            $this->channel->queue_declare($this->queue, $this->passive, $this->durable, $this->exclusive, $this->autoDelete, $this->nowait, $this->arguments);
            $this->channel->queue_bind($this->queue, $this->exchange, $this->routingKey);
        } else {
            $this->channel->queue_declare($this->queue, $this->passive, $this->durable, $this->exclusive, $this->autoDelete, $this->nowait, $this->arguments);
        }
    }

    public function queue_declare_rpc(): array
    {
        return $this->channel->queue_declare("", $this->passive, $this->durable, $this->exclusive, $this->autoDelete);
    }

    public function onResponse($response)
    {
        if ($response->get('correlation_id') == $this->correlation_id) {
            $this->response = $response->body;
        }
    }

    public function destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
