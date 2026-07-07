<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Services\RabbitMQ;

use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Exception\AMQPChannelClosedException;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPTimeoutException;

class RabbitMQService extends RabbitMQ
{
    public function __construct($queue, $routingKey, $exchange = '', $exchangeType = '', $consumerTag = null, $passive = false, $durable = true, $exclusive = false, $autoDelete = false, array $arguments = [])
    {
        parent::__construct($queue, $routingKey, $exchange, $exchangeType, $consumerTag, $passive, $durable, $exclusive, $autoDelete, $arguments);
    }

    public function publish(string $message)
    {
        $this->queue_declare();

        try {
            $msg = new AMQPMessage($message, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
            $this->channel->basic_publish($msg, $this->exchange, $this->routingKey);
        } catch (AMQPConnectionClosedException|AMQPChannelClosedException $exception) {
            $this->reconnect();
            $this->queue_declare();

            $msg = new AMQPMessage($message, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
            $this->channel->basic_publish($msg, $this->exchange, $this->routingKey);
        } catch (\Throwable $th) {
            Log::error(__METHOD__ . ' ' . __LINE__,  ['context' => $th->getMessage()]);
            throw $th;
        }
    }

    public function publishBatch(array $messages)
    {
        $this->queue_declare();

        try {
            foreach ($messages as $message) {
                $msg = new AMQPMessage(json_encode($message), array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
                $this->channel->batch_basic_publish($msg, $this->exchange, $this->routingKey);
            }

            $this->channel->publish_batch();
        } catch (AMQPConnectionClosedException|AMQPChannelClosedException $exception) {
            $this->reconnect();
            $this->queue_declare();

            foreach ($messages as $message) {
                $msg = new AMQPMessage(json_encode($message), array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
                $this->channel->batch_basic_publish($msg, $this->exchange, $this->routingKey);
            }

            $this->channel->publish_batch();
        } catch (\Throwable $th) {
            Log::error(__METHOD__.' '.__LINE__, ['context' => $th->getMessage()]);
            throw $th;
        }
    }

    public function publishRpc(string $message, int $timeout = 15)
    {
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

        try {
            while (!$this->response) {
                $this->channel->wait(null, false, $timeout);
            }
        } catch (AMQPTimeoutException $e) {
            $this->response = null;
        }

        return $this->response;
    }

    public function consume(callable $callback, ?int $timeout = null)
    {
        try {
            $this->queue_declare();
            $this->channel->basic_qos(null, 1, false);
            $this->channel->basic_consume($this->queue, $this->consumerTag, false, false, false, false, $callback);

            if ($timeout) {
                while ($this->channel->is_consuming()) {
                    $this->channel->wait(null, false, $timeout);
                }
            } else {
                $this->channel->consume();
            }
        } catch (AMQPConnectionClosedException|AMQPChannelClosedException $exception) {
            Log::warning(__METHOD__ . ' ' . __LINE__, ['context' => $exception->getMessage()]);

            throw new AMQPRuntimeException(
                'Lost connection: ' . $exception->getMessage(),
                $exception->getCode(),
                $exception
            );
        } catch (\Throwable $th) {
            Log::warning(__METHOD__ . ' ' . __LINE__,  ['context' => $th->getMessage()]);
            throw $th;
        }
    }
}
