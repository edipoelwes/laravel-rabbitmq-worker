<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Services\RabbitMQ;

use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Exception\AMQPChannelClosedException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Exception\AMQPSocketException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQService extends RabbitMQ
{
    private ?int $confirmChannelId = null;

    public function __construct($queue, $routingKey, $exchange = '', $exchangeType = '', $consumerTag = null, $passive = false, $durable = true, $exclusive = false, $autoDelete = false, array $arguments = [])
    {
        parent::__construct($queue, $routingKey, $exchange, $exchangeType, $consumerTag, $passive, $durable, $exclusive, $autoDelete, $arguments);
    }

    public function publish(string $message)
    {
        $this->publishWithHeaders($message);
    }

    public function publishWithHeaders(string $message, array $headers = [])
    {
        try {
            $this->publishWithFailover(function () use ($message, $headers) {
                $this->queue_declare();
                $this->ensurePublisherConfirms();

                $msg = new AMQPMessage($message, $this->buildMessageProperties($headers));
                $this->channel->basic_publish($msg, $this->exchange, $this->routingKey);

                $this->awaitPublisherConfirms();
            });
        } catch (\Throwable $th) {
            Log::error(__METHOD__ . ' ' . __LINE__,  ['context' => $th->getMessage()]);
            throw $th;
        }
    }

    public function publishBatch(array $messages)
    {
        $this->publishBatchWithHeaders($messages);
    }

    public function publishBatchWithHeaders(array $messages, array $headers = [])
    {
        try {
            $this->publishWithFailover(function () use ($messages, $headers) {
                $this->queue_declare();
                $this->ensurePublisherConfirms();

                foreach ($messages as $message) {
                    $msg = new AMQPMessage(json_encode($message), $this->buildMessageProperties($headers));
                    $this->channel->batch_basic_publish($msg, $this->exchange, $this->routingKey);
                }

                $this->channel->publish_batch();

                $this->awaitPublisherConfirms();
            });
        } catch (\Throwable $th) {
            Log::error(__METHOD__.' '.__LINE__, ['context' => $th->getMessage()]);
            throw $th;
        }
    }

    public function publishRpc(string $message, int $timeout = 15)
    {
        $this->publishWithFailover(function () use ($message) {
            list($queue_name) = $this->queue_declare_rpc();

            $this->channel->basic_consume(
                $queue_name,
                '',
                false,
                true,
                false,
                false,
                array(
                    $this,
                    'onResponse'
                )
            );

            $msg = new AMQPMessage(
                $message,
                array(
                    'delivery_mode' => AMQPMessage::DELIVERY_MODE_NON_PERSISTENT,
                    'content_type' => 'application/json',
                    'reply_to' => $queue_name,
                    'correlation_id' => $this->correlation_id
                )
            );

            $this->channel->basic_publish($msg, '', $this->queue);
        });

        try {
            while (!$this->response) {
                $this->channel->wait(null, false, $timeout);
            }
        } catch (AMQPTimeoutException $e) {
            $this->response = null;
        }

        return $this->response;
    }

    /**
     * Executa a operação de publicação com failover: qualquer falha de
     * conexão/IO coloca o host atual em cooldown e a tentativa seguinte
     * reconecta direto em um nó saudável do cluster.
     */
    private function publishWithFailover(callable $operation)
    {
        $attempts = 0;
        $maxAttempts = max(1, (int) config('laravel-rabbitmq-worker.publisher.max_attempts', 3));

        while (true) {
            try {
                $this->ensureConnected();

                return $operation();
            } catch (AMQPRuntimeException|AMQPIOException|AMQPTimeoutException $exception) {
                $attempts++;
                $this->markConnectedHostAsFailed($exception->getMessage());

                Log::warning('[LaravelRabbitmqWorker] Publish attempt failed, retrying on a healthy host.', [
                    'queue' => $this->queue,
                    'attempt' => $attempts,
                    'error' => $exception->getMessage(),
                ]);

                if ($attempts >= $maxAttempts) {
                    throw $exception;
                }

                $this->closeResources();
            }
        }
    }

    private function buildMessageProperties(array $headers = []): array
    {
        $properties = [
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
        ];

        if (!empty($headers)) {
            $properties['application_headers'] = new AMQPTable($headers);
        }

        return $properties;
    }

    protected function closeResources(): void
    {
        $this->confirmChannelId = null;
        parent::closeResources();
    }

    private function ensurePublisherConfirms(): void
    {
        if (! $this->publisherConfirmsEnabled()) {
            return;
        }

        $channelId = spl_object_id($this->channel);

        if ($this->confirmChannelId !== $channelId) {
            $this->channel->confirm_select();
            $this->confirmChannelId = $channelId;
        }
    }

    private function awaitPublisherConfirms(): void
    {
        if ($this->publisherConfirmsEnabled()) {
            $this->channel->wait_for_pending_acks(
                max(1, (int) config('laravel-rabbitmq-worker.publisher.confirm_timeout_seconds', 5))
            );
        }
    }

    private function publisherConfirmsEnabled(): bool
    {
        return (bool) config('laravel-rabbitmq-worker.publisher.confirm', false);
    }

    public function consume(callable $callback, ?int $timeout = null)
    {
        $attempts = 0;

        while (true) {
            try {
                $this->ensureConnected();

                $this->queue_declare();
                $this->channel->basic_qos(null, 1, false);
                $this->channel->basic_consume($this->queue, $this->consumerTag, false, false, false, false, $callback);

                while ($this->channel->is_consuming()) {
                    try {
                        $this->channel->wait(null, false, $timeout ?? $this->consumerWaitTimeout());
                        $attempts = 0;
                    } catch (AMQPTimeoutException $exception) {
                        if ($timeout !== null) {
                            // Timeout explícito mantém o contrato original: encerra o consumo.
                            throw $exception;
                        }

                        // Fila ociosa dentro da janela de espera: valida a conexão para
                        // detectar nó que caiu sem fechar o socket.
                        try {
                            $this->connection->checkHeartBeat();
                        } catch (AMQPTimeoutException $heartbeatTimeout) {
                            throw new AMQPConnectionClosedException(
                                $heartbeatTimeout->getMessage(),
                                (int) $heartbeatTimeout->getCode(),
                                $heartbeatTimeout
                            );
                        }
                    }
                }

                return;
            } catch (AMQPConnectionClosedException|AMQPChannelClosedException|AMQPSocketException|AMQPIOException|ClusterConnectionException $exception) {
                $attempts++;

                if (! $exception instanceof ClusterConnectionException) {
                    $this->markConnectedHostAsFailed($exception->getMessage());
                }

                Log::warning('[LaravelRabbitmqWorker] Consumer lost connection, retrying on a healthy host.', [
                    'queue' => $this->queue,
                    'attempt' => $attempts,
                    'error' => $exception->getMessage(),
                ]);

                $maxAttempts = $this->maxReconnectAttempts();
                if ($maxAttempts > 0 && $attempts >= $maxAttempts) {
                    throw new AMQPRuntimeException(
                        'Lost connection: ' . $exception->getMessage(),
                        (int) $exception->getCode(),
                        $exception
                    );
                }

                $this->closeResources();
                sleep($this->reconnectDelaySeconds($attempts));
            } catch (\Throwable $th) {
                Log::warning(__METHOD__ . ' ' . __LINE__,  ['context' => $th->getMessage()]);
                throw $th;
            }
        }
    }

    private function consumerWaitTimeout(): int
    {
        return max(1, (int) config('laravel-rabbitmq-worker.consumer.wait_timeout_seconds', 10));
    }

    private function maxReconnectAttempts(): int
    {
        return max(0, (int) config('laravel-rabbitmq-worker.consumer.max_reconnect_attempts', 0));
    }

    private function reconnectDelaySeconds(int $attempt): int
    {
        $base = max(1, (int) config('laravel-rabbitmq-worker.consumer.reconnect_base_delay_seconds', 1));
        $max = max($base, (int) config('laravel-rabbitmq-worker.consumer.reconnect_max_delay_seconds', 30));

        return (int) min($base * (2 ** max(0, $attempt - 1)), $max);
    }
}
