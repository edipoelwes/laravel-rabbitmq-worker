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
];
