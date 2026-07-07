<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Services\RabbitMQ;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPChannelClosedException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use RuntimeException;
use Throwable;

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
    protected array $connectedHost = [];

    public function __construct(
        $queue,
        $routingKey,
        $exchange,
        $exchangeType,
        $consumerTag,
        $passive,
        $durable,
        $exclusive,
        $autoDelete,
        array $arguments = []
    )
    {
        $this->queue = $queue;
        $this->exchange = $exchange;
        $this->routingKey = $routingKey;
        $this->exchangeType = $exchangeType;
        $this->consumerTag = is_null($consumerTag) ? '' : 'amq.tag.' . $consumerTag;
        $this->passive = $passive;
        $this->exclusive = $exclusive;
        $this->durable = $durable;
        $this->autoDelete = $autoDelete;
        $this->arguments = array_merge($this->arguments, $arguments);
        $this->correlation_id = Str::uuid();

        $this->connectToCluster();
        $this->channel = $this->connection->channel();
    }

    public function queue_declare()
    {
        if (!empty($this->exchange)) {
            $this->channel->exchange_declare($this->exchange, $this->exchangeType, $this->passive, $this->durable, $this->autoDelete);
            $this->channel->queue_declare($this->queue, $this->passive, $this->durable, $this->exclusive, $this->autoDelete, $this->nowait, $this->arguments);
            $this->channel->queue_bind($this->queue, $this->exchange, $this->routingKey);
        } else {
            $this->channel->queue_declare($this->queue, $this->passive, $this->durable, $this->exclusive, $this->autoDelete, $this->nowait, $this->arguments);
        }
    }

    public function queue_declare_rpc(): array
    {
        return $this->channel->queue_declare('', $this->passive, $this->durable, $this->exclusive, $this->autoDelete);
    }

    public function onResponse($response)
    {
        if ($response->get('correlation_id') == $this->correlation_id) {
            $this->response = $response->body;
        }
    }

    public function destruct()
    {
        if ($this->channel) {
            $this->channel->close();
        }

        if ($this->connection) {
            $this->connection->close();
        }
    }

    public function connectedHost(): array
    {
        // Apenas chaves de identificação do nó; credenciais (password,
        // login_response) nunca devem sair por um getter público.
        return array_intersect_key($this->connectedHost, array_flip(['host', 'port', 'vhost', 'user']));
    }

    protected function connectToCluster(): void
    {
        $connectionConfig = (array) config('laravel-rabbitmq-worker.connections', []);
        $clusterConfig = (array) config('laravel-rabbitmq-worker.cluster', []);
        $selector = new ClusterHostSelector($connectionConfig, $clusterConfig);

        $latestException = null;
        $errors = [];

        foreach ($selector->orderedHosts() as $hostDefinition) {
            try {
                $this->connection = new AMQPStreamConnection(
                    $hostDefinition['host'],
                    $hostDefinition['port'],
                    $hostDefinition['user'],
                    $hostDefinition['password'],
                    $hostDefinition['vhost'],
                    $hostDefinition['insist'],
                    $hostDefinition['login_method'],
                    $hostDefinition['login_response'],
                    $hostDefinition['locale'],
                    $hostDefinition['connection_timeout'],
                    $hostDefinition['read_write_timeout'],
                    $hostDefinition['context'],
                    $hostDefinition['keepalive'],
                    $hostDefinition['heartbeat'],
                    $hostDefinition['channel_rpc_timeout'],
                    $hostDefinition['ssl_protocol']
                );

                $this->connectedHost = $hostDefinition;
                $selector->rememberSuccessfulHost($hostDefinition);

                Log::debug('[LaravelRabbitmqWorker] Connected to RabbitMQ host.', [
                    'host' => $hostDefinition['host'],
                    'port' => $hostDefinition['port'],
                    'queue' => $this->queue,
                ]);

                return;
            } catch (Throwable $exception) {
                $latestException = $exception;
                $selector->rememberFailedHost($hostDefinition, $exception->getMessage());
                $errors[] = sprintf('%s:%s (%s)', $hostDefinition['host'], $hostDefinition['port'], $exception->getMessage());

                Log::warning('[LaravelRabbitmqWorker] Failed to connect to RabbitMQ host.', [
                    'host' => $hostDefinition['host'],
                    'port' => $hostDefinition['port'],
                    'queue' => $this->queue,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        throw new RuntimeException(
            'RabbitMQ cluster connection failed for all configured hosts. Attempts: ' . implode(' | ', $errors),
            0,
            $latestException
        );
    }

    /**
     * Reconnects using the original configuration and recreates the main channel.
     *
     * @throws Throwable
     */
    protected function reconnect(): void
    {
        $this->closeResources();
        $this->connectToCluster();
        $this->channel = $this->connection->channel();
    }

    protected function createFreshChannel()
    {
        try {
            return $this->connection->channel();
        } catch (AMQPConnectionClosedException|AMQPChannelClosedException $exception) {
            $this->reconnect();

            return $this->connection->channel();
        }
    }

    private function closeResources(): void
    {
        try {
            if ($this->channel) {
                $this->channel->close();
            }
        } catch (Throwable $exception) {
            // Ignore cleanup failures during reconnect.
        } finally {
            $this->channel = null;
        }

        try {
            if ($this->connection) {
                $this->connection->close();
            }
        } catch (Throwable $exception) {
            // Ignore cleanup failures during reconnect.
        } finally {
            $this->connection = null;
        }
    }
}
