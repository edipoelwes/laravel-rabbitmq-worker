<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Services\RabbitMQ;

use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQService extends RabbitMQ
{
    public function __construct($queue, $routingKey, $exchange = '', $exchangeType = '', $consumerTag = null, $passive = false, $durable = true, $exclusive = false, $autoDelete = false) {
        parent::__construct($queue, $routingKey, $exchange, $exchangeType, $consumerTag, $passive, $durable, $exclusive, $autoDelete);
    }

    public function publish(string $message)
    {
        $this->queue_declare();

        try {
            $msg = new AMQPMessage($message, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
            $this->channel->basic_publish($msg, $this->exchange, $this->routingKey);
        } catch (\Throwable $th) {
            Log::error(__METHOD__.' '.__LINE__,  ['context' => $th->getMessage()]);
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
        } catch (\Throwable $th) {
            Log::error(__METHOD__.' '.__LINE__, ['context' => $th->getMessage()]);
        }
    }

    public function publishRpc(string $message)
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

        while (!$this->response) {
            $this->channel->wait();
        }

        return $this->response;
    }

    public function consume(callable $callback, int $timeout = null)
    {
        $this->queue_declare();
        $this->channel->basic_qos(null, 1, false);
        $this->channel->basic_consume($this->queue, $this->consumerTag, false, false, false, false, $callback);

        try {
            if($timeout) {
                while ($this->channel->is_consuming())
                    $this->channel->wait(null, false, $timeout);
            } else {
                $this->channel->consume();
            }
        } catch (\Throwable $th) {
            Log::warning(__METHOD__.' '.__LINE__,  ['context' => $th->getMessage()]);
        }
    }
}
