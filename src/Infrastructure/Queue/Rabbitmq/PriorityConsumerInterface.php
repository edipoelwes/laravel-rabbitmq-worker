<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq;

/**
 * Contrato dos consumers registrados em
 * config('laravel-rabbitmq-worker.priority.routes'). O PriorityMessageRouter
 * delega cada mensagem para process($message), que é responsável pelo
 * ack/nack/reject conforme a regra de negócio.
 *
 * A interface é opcional (o router aceita qualquer classe com process(), para
 * permitir reaproveitar consumers legados), mas recomendada para código novo.
 */
interface PriorityConsumerInterface
{
    /**
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public function process($message): void;
}
