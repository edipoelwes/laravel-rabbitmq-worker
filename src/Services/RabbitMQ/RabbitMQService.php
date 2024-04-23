<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Services\RabbitMQ;

use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQService extends RabbitMQ
{
    public function __construct($queue, $routingKey, $exchange = '', $exchangeType = '') {
        parent::__construct($queue, $routingKey, $exchange, $exchangeType);
    }
    public function publish($message)
    {
        try {
            $msg = new AMQPMessage($message, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
            $this->channel->basic_publish($msg, $this->exchange, $this->routingKey);
        } catch (\Throwable $th) {
            Log::error(__METHOD__.' '.__LINE__,  ['context' => $th->getMessage()]);
        }
    }

    public function consume(callable $callback, int $timeout = null)
    {
        $this->channel->basic_qos(null, 1, false);
        $this->channel->basic_consume($this->queue, '', false, false, false, false, $callback);

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
