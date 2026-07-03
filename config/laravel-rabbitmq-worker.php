<?php

// RABBITMQ_HOSTS é a única fonte de hosts: um item para ambiente single-node
// (ex.: "rabbitmq") ou uma lista separada por vírgula para cluster.
$rabbitMqHosts = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('RABBITMQ_HOSTS', 'localhost'))
)));

return [
    'connections' => [
        'host' => $rabbitMqHosts[0] ?? 'localhost',
        'hosts' => array_map(static function (string $host): array {
            return ['host' => $host];
        }, $rabbitMqHosts),
        'port' => env('RABBITMQ_PORT', 5672),
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
        'heartbeat' => (int) env('RABBITMQ_HEARTBEAT', 30),
        'channel_rpc_timeout' => (float) env('RABBITMQ_CHANNEL_RPC_TIMEOUUT', 0.0),
        'ssl_protocol' => env('RABBITMQ_SSL_PROTOCOL', null),
    ],
    'cluster' => [
        'last_host_cache_key' => env('RABBITMQ_LAST_HOST_CACHE_KEY', 'rabbitmq:cluster:last-success-host'),
        'last_index_cache_key' => env('RABBITMQ_LAST_INDEX_CACHE_KEY', 'rabbitmq:cluster:last-success-index'),
    ],
];
