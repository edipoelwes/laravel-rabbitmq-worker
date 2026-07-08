<?php

namespace Edipoelwes\LaravelRabbitmqWorker\Commands;

use Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq\QueueProducer;
use Illuminate\Console\Command;

/**
 * Publisher genérico para teste manual do fluxo de filas por prioridade.
 * Permite publicar qualquer message_type com payload arbitrário, sem escrever
 * código: útil para validar uma rota nova de
 * config('laravel-rabbitmq-worker.priority.routes') antes de migrar o
 * producer definitivo.
 *
 * Exemplos:
 *   php artisan rabbitmq:priority-publish meu_message_type
 *   php artisan rabbitmq:priority-publish meu_message_type --payload='{"id":123}'
 *   php artisan rabbitmq:priority-publish meu_message_type --priority=low --count=5
 */
class PublishPriorityMessageCommand extends Command
{
    protected $signature = 'rabbitmq:priority-publish
                            {message_type : Header AMQP message_type (deve estar mapeado em priority.routes)}
                            {--priority= : Prioridade da fila (high|default|low). Padrão: a da rota no config, senão default}
                            {--payload= : Payload JSON do corpo da mensagem. Padrão: {"ping_id":1} }
                            {--count=1 : Quantidade de mensagens (count > 1 usa publicação em lote)}';

    protected $description = 'Publica mensagem(ns) de teste manual na fila de prioridade do RabbitMQ';

    public function handle(QueueProducer $producer): int
    {
        $messageType = $this->argument('message_type');
        $count = max(1, (int) $this->option('count'));

        $payload = json_decode($this->option('payload') ?: '{"ping_id":1}', true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
            $this->error('Opção --payload deve ser um JSON válido de objeto. Ex.: --payload=\'{"id":123}\'');
            return self::FAILURE;
        }

        $route = config("laravel-rabbitmq-worker.priority.routes.{$messageType}");
        if (!$route) {
            $this->warn("message_type '{$messageType}' NÃO está mapeado em priority.routes.");
            $this->warn('A mensagem será publicada, mas o PriorityMessageRouter vai rejeitá-la (reject sem requeue).');
            if (!$this->confirm('Publicar mesmo assim?')) {
                return self::FAILURE;
            }
        }

        $priority = $this->option('priority') ?: ($route['priority'] ?? 'default');

        if ($count === 1) {
            $producer->producePriority($priority, $messageType, $payload);
        } else {
            $producer->producePriorityBatch($priority, $messageType, array_fill(0, $count, $payload));
        }

        $queue = config("laravel-rabbitmq-worker.priority.queues.{$priority}");
        $this->info("Publicada(s) {$count} mensagem(ns) na fila {$queue}:");
        $this->line("  message_type: {$messageType}");
        $this->line("  consumer:     " . ($route['consumer'] ?? 'NENHUM (será rejeitada)'));
        $this->line("  payload:      " . json_encode($payload));

        return self::SUCCESS;
    }
}
