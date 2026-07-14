<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq;

use Illuminate\Support\Facades\Log;

/**
 * Porta de entrada única dos consumers de prioridade (high/default/low).
 * Lê o header AMQP `message_type` e delega o processamento ao consumer
 * mapeado em config('laravel-rabbitmq-worker.priority.routes'), preservando
 * a regra de negócio existente em cada consumer::process().
 *
 * Mensagens sem header ou com tipo não mapeado são rejeitadas sem requeue
 * (reject(false)) e logadas — a falha é tratada perto de onde ocorre, sem
 * travar a fila.
 */
class PriorityMessageRouter
{
    public function dispatch($message): void
    {
        $messageType = $this->resolveMessageType($message);

        if (!$messageType) {
            Log::error('[PriorityMessageRouter] Header message_type ausente.', ['body' => $message->body]);
            $message->reject(false);
            return;
        }

        $consumerClass = config("laravel-rabbitmq-worker.priority.routes.{$messageType}.consumer");

        if (!$consumerClass) {
            Log::error('[PriorityMessageRouter] Tipo de mensagem não mapeado.', [
                'message_type' => $messageType,
                'body' => $message->body,
            ]);
            $message->reject(false);
            return;
        }

        $consumer = app($consumerClass);

        if (!is_callable([$consumer, 'process'])) {
            Log::error('[PriorityMessageRouter] Consumer mapeado não possui process().', [
                'message_type' => $messageType,
                'consumer' => $consumerClass,
            ]);
            $message->reject(false);
            return;
        }

        $consumer->process($message);
    }

    private function resolveMessageType($message): ?string
    {
        if (!$message->has('application_headers')) {
            return null;
        }

        $headers = $message->get('application_headers')->getNativeData();

        return $headers['message_type'] ?? null;
    }
}
