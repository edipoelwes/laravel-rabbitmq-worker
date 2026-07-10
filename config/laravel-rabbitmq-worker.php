<?php

// RABBITMQ_HOSTS é a fonte dos nós AMQP usados pela aplicação. Aceita um item
// para single-node ou uma lista separada por vírgula para cluster.
// RABBITMQ_HOST permanece apenas como fallback legado / infra.
$rabbitMqHosts = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('RABBITMQ_HOSTS', (string) env('RABBITMQ_HOST', 'localhost')))
)));

$rabbitMqManagementUrls = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('RABBITMQ_MGMT_URLS', ''))
)));

$rabbitMqNodes = array_map(static function (string $host): array {
    return [
        'host' => $host,
        'port' => (int) env('RABBITMQ_PORT', 5672),
    ];
}, $rabbitMqHosts);

if ($rabbitMqNodes === []) {
    $rabbitMqNodes = [[
        'host' => 'localhost',
        'port' => (int) env('RABBITMQ_PORT', 5672),
    ]];
}

return [
    /*
     * Prefixo dos commands Artisan de consumo por prioridade fornecidos pela
     * lib. O nome final registrado é "<prefix>_priority_<high|default|low>".
     * Ex.: RABBITMQ_COMMAND_PREFIX=dasa registra dasa_priority_high,
     * dasa_priority_default e dasa_priority_low.
     */
    'command_prefix' => env('RABBITMQ_COMMAND_PREFIX', 'rabbitmq'),

    'connections' => [
        'host' => $rabbitMqNodes[0]['host'],
        'hosts' => $rabbitMqNodes,
        'port' => $rabbitMqNodes[0]['port'],
        'user' => env('RABBITMQ_LOGIN', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'insist' => env('RABBITMQ_INSIT', false),
        'login_method' => env('RABBITMQ_LOGIN_METHOD', 'AMQPLAIN'),
        'login_response' => env('RABBITMQ_LOGIN_RESPOSE', null),
        'locale' => env('RABBITMQ_LOCALE', 'en_US'),
        'connection_timeout' => (float) env('RABBITMQ_CONNECTION_TIMEOUT', 3.0),
        'read_write_timeout' => (float) env('RABBITMQ_READ_WRITE_TIMEOUT', 3.0),
        'context' => env('RABBITMQ_CONTEXT', null),
        'keepalive' => env('RABBITMQ_KEEPALIVE', false),
        'heartbeat' => (int) env('RABBITMQ_HEARTBEAT', 60),
        'channel_rpc_timeout' => (float) env('RABBITMQ_CHANNEL_RPC_TIMEOUUT', 0.0),
        'ssl_protocol' => env('RABBITMQ_SSL_PROTOCOL', null),
    ],
    'management' => [
        'urls' => $rabbitMqManagementUrls,
    ],
    'cluster' => [
        'last_host_cache_key' => env('RABBITMQ_LAST_HOST_CACHE_KEY', 'rabbitmq:cluster:last-success-host'),
        'last_index_cache_key' => env('RABBITMQ_LAST_INDEX_CACHE_KEY', 'rabbitmq:cluster:last-success-index'),
        'failed_host_cache_prefix' => env('RABBITMQ_FAILED_HOST_CACHE_PREFIX', 'rabbitmq:cluster:failed-host:'),
        'failed_host_base_cooldown_seconds' => (int) env('RABBITMQ_FAILED_HOST_BASE_COOLDOWN_SECONDS', 30),
        'failed_host_max_cooldown_seconds' => (int) env('RABBITMQ_FAILED_HOST_MAX_COOLDOWN_SECONDS', 300),
        'failed_host_probe_every' => (int) env('RABBITMQ_FAILED_HOST_PROBE_EVERY', 10),
        'attempt_counter_cache_key' => env('RABBITMQ_ATTEMPT_COUNTER_CACHE_KEY', 'rabbitmq:cluster:connection-attempt-counter'),
    ],

    /*
     * Consolidação de filas por prioridade: em vez de uma fila dedicada por
     * ação, as mensagens são publicadas em 3 filas físicas (high/default/low)
     * com o header AMQP `message_type` identificando o tipo funcional. O
     * PriorityMessageRouter resolve o consumer em `routes` e delega para
     * consumer::process($message).
     *
     * ATENÇÃO: mergeConfigFrom faz merge raso (primeiro nível). Se a aplicação
     * definir a chave `priority` no config publicado, deve definir o bloco
     * completo (queues + routes).
     */
    'priority' => [
        'queues' => [
            'high' => env('RABBITMQ_PRIORITY_QUEUE_HIGH', 'priority_high'),
            'default' => env('RABBITMQ_PRIORITY_QUEUE_DEFAULT', 'priority_default'),
            'low' => env('RABBITMQ_PRIORITY_QUEUE_LOW', 'priority_low'),
        ],

        /*
         * DLQ por fila física de prioridade (não por message_type). Quando
         * habilitada, a fila principal é declarada com x-dead-letter-exchange,
         * x-dead-letter-routing-key e x-delivery-limit apontando para
         * "<fila>.<suffix>", e a lib garante que essa fila exista antes do
         * consumo. `priorities.<high|default|low>` permite sobrescrever
         * enabled/suffix/delivery_limit/queue_type por prioridade; chaves
         * ausentes caem para o valor global acima.
         */
        'dead_letter' => [
            'enabled' => env('RABBITMQ_PRIORITY_DLQ_ENABLED', true),
            'suffix' => env('RABBITMQ_PRIORITY_DLQ_SUFFIX', '.dlq'),
            'delivery_limit' => (int) env('RABBITMQ_PRIORITY_DELIVERY_LIMIT', 3),
            'queue_type' => env('RABBITMQ_PRIORITY_DLQ_QUEUE_TYPE', 'quorum'),
            'priorities' => [
                'high' => [],
                'default' => [],
                'low' => [],
            ],
        ],

        /*
         * Mapa de roteamento da aplicação: message_type => [
         *     'priority' => 'high'|'default'|'low',
         *     'consumer' => classe com process($message) (PriorityConsumerInterface),
         * ]
         */
        'routes' => [],
    ],
];
