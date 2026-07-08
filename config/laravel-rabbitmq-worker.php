<?php

return [
    /*
     * Prefixo dos commands Artisan de consumo por prioridade fornecidos pela
     * lib. O nome final registrado é "<prefix>_priority_<high|default|low>".
     * Ex.: RABBITMQ_COMMAND_PREFIX=dasa registra dasa_priority_high,
     * dasa_priority_default e dasa_priority_low.
     */
    'command_prefix' => env('RABBITMQ_COMMAND_PREFIX', 'rabbitmq'),

    'connections' => [
        'host' => env('RABBITMQ_HOST', 'localhost'),
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
