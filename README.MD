# laravel-rabbitmq-worker
## Library to facilitate the use of rabbitmq within php based on the [php-amqplib](https://github.com/php-amqplib/php-amqplib) library.

##
# Installing

```
composer require edipoelwes/laravel-rabbitmq-worker
```

## How to configure in Laravel
#### Run the publisher to generate configuration file
When you run the publisher command, it automatically creates a configuration file named `laravel-rabbitmq-worker.php` within the `config` directory of your Laravel application.

```bash
php artisan vendor:publish --provider="Edipoelwes\LaravelRabbitmqWorker\CommandServiceProvider"
```

## Clear settings cache

Before configuring the RabbitMQ connection settings according to your environment, it's essential to clear the Laravel configuration cache to ensure that any changes take effect properly.

```bash
php artisan config:cache
```

Then just configure according to your environment.

```php

<?php

return [
    'connections' => [
        'host' => env('RABBITMQ_HOST', 'localhost'),
        'port' => env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_LOGIN', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
    ]
];
```

## Usage examples

### Creating a simple publisher
```php

<?php

use Edipoelwes\LaravelRabbitmqWorker\Services\RabbitMQ\RabbitMQService;

$rabbitMQService = new RabbitMQService(
    'queue',       // Queue
    'route-key',   // Routing key
    '',            // Exchange
    '',            // Exchange Type
    '',            // Consumer Tag
    false,         // Passive
    true,          // Durable
    false,         // Exclusive
    false          // Auto delete
);

// Prepare response payload
$payload = "your message";

$rabbitMQService->publish($payload);
$rabbitMQService->destruct(); // Clean up resources
```

### Publishing with message headers

Use message headers when your consumer routing depends on AMQP `application_headers`. This is separate from queue declaration arguments.

```php
<?php

use Edipoelwes\LaravelRabbitmqWorker\Services\RabbitMQ\RabbitMQService;

$rabbitMQService = new RabbitMQService(
    'dasa_priority_low',
    'dasa_priority_low'
);

$rabbitMQService->publishWithHeaders(
    json_encode(['fiad_id' => 123]),
    ['message_type' => 'dasa_update_pbzpa']
);

$rabbitMQService->destruct();
```

### Publishing a batch with message headers

```php
<?php

use Edipoelwes\LaravelRabbitmqWorker\Services\RabbitMQ\RabbitMQService;

$rabbitMQService = new RabbitMQService(
    'dasa_priority_low',
    'dasa_priority_low'
);

$rabbitMQService->publishBatchWithHeaders(
    [
        ['fiad_id' => 1],
        ['fiad_id' => 2],
    ],
    ['message_type' => 'dasa_update_pbzpa']
);

$rabbitMQService->destruct();
```

### Creating a consumer

```php

<?php

use Edipoelwes\LaravelRabbitmqWorker\Services\RabbitMQ\RabbitMQService;

$rabbitMQService = new RabbitMQService(
    'queue',       // Queue
    'route-key',   // Routing key
    '',            // Exchange
    '',            // Exchange Type
    '',            // Consumer Tag
    false,         // Passive
    true,          // Durable
    false,         // Exclusive
    false,         // Auto delete
    []             // Custom arguments (optional)
);

$callback = function ($msg) {
    //  $msg->body
    // your code here
    
    $msg->ack();
}

$rabbitMQService->consume($callback);
$rabbitMQService->destruct(); // Clean up resources

```

### Creating a Consumer Using QueueConsumerAbstract

#### You can create a consumer by extending the `QueueConsumerAbstract` class. This approach allows you to handle messages in a structured way. Below is an example of how to implement a command that consumes messages from a RabbitMQ queue:

```php
<?php

namespace App\Console\Commands;

use Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq\QueueConsumerAbstract;
use Illuminate\Support\Facades\Log;

class RabbitmqTesting extends QueueConsumerAbstract
{
    protected $signature = 'hello-command';

    protected string $queueName = 'hello';

    protected string $routeKey = 'hello';

    protected string $consumerTag = 'rabbitmq-command-testing';

    // Optional: override $arguments to set custom queue configuration (TTL, DLQ, delivery limit, etc.)
    // See "Custom Queue Arguments" section for examples.

    /**
     * @throws \Exception
     */
    public function process($message): void
    {
        Log::info('start handling');
        Log::info($message->body);
        $message->ack();
        Log::info('finish handling');
    }
}

```


### Creating a consumer into laravel command
```php

<?php

namespace App\Console\Commands;

use Edipoelwes\LaravelRabbitmqWorker\Services\RabbitMQ\RabbitMQService;
use Illuminate\Console\Command;

class Consume extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'your-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $rabbitMQService = new RabbitMQService(
            'queue',     // Queue
            'route-key', // Routing key
            '',          // Exchange
            '',          // Exchange Type
            '',          // Consumer Tag
            false,       // Passive
            true,        // Durable
            false,       // Exclusive
            false,       // Auto delete
            []           // Custom arguments (optional)
        );

        $callback = function ($msg) {
            //  $msg->body
            // your code here
            
            $msg->ack();
        }
        
        $rabbitMQService->consume($callback);
        $rabbitMQService->destruct(); // Clean up resources
    }
}

```

### Custom Queue Arguments

You can pass custom RabbitMQ queue arguments to the consumer via the `$arguments` parameter in `RabbitMQService` or through `QueueBuilder::setArguments()`. This is useful for features like message TTL, dead-letter exchanges, delivery limits, and more.

Each argument follows the AMQP type notation: `'argument-name' => ['type', value]`, where the type is `'S'` for string and `'I'` for integer.

> **`setArguments()` replaces** the arguments array entirely — what you pass is exactly what reaches the broker (merged with the base default `x-queue-type: quorum` in the constructor). To override the default, include `x-queue-type` in your own arguments.

### Quorum replication (`x-quorum-initial-group-size`)

Whenever the final queue type is `quorum`, the lib also sends
`x-quorum-initial-group-size` so the queue is born replicated across the
expected number of cluster nodes. The value comes from
`config('laravel-rabbitmq-worker.quorum.initial_group_size')`:

```
RABBITMQ_QUORUM_INITIAL_GROUP_SIZE=3   # default: 3 (set 0 to omit the argument)
```

Notes:

- The argument only takes effect **when the queue is created**. Queues that
  already exist on the broker keep their current members — grow them with
  `rabbitmq-queues grow <node> all` or delete/redeclare the queue.
- With 3 replicas a quorum queue tolerates 1 node down (majority of 2); a
  queue created with a single member becomes unavailable whenever its node
  goes down.
- Passing `x-quorum-initial-group-size` explicitly in your own arguments takes
  precedence over the config value.

Using `QueueConsumerAbstract`, override the `$arguments` property in your subclass:

```php
<?php

namespace App\Console\Commands;

use Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq\QueueConsumerAbstract;

class RabbitmqTesting extends QueueConsumerAbstract
{
    protected $signature = 'hello-command';

    protected string $queueName = 'hello';

    protected string $routeKey = 'hello';

    protected string $consumerTag = 'rabbitmq-command-testing';

    protected array $arguments = [
        'x-message-ttl'             => ['I', 15000],                    // TTL in ms (integer)
        'x-dead-letter-exchange'    => ['S', ''],                       // dead-letter exchange (string)
        'x-dead-letter-routing-key' => ['S', 'your-queue-name.dlq'],    // DLQ routing key (string)
        'x-delivery-limit'          => ['I', 3],                        // retry 3x, on 4th goes to DLQ
    ];

    public function process($message): void
    {
        // your code here
        $message->ack();
    }
}
```

Using `RabbitMQService` directly:

```php
<?php

use Edipoelwes\LaravelRabbitmqWorker\Services\RabbitMQ\RabbitMQService;

$rabbitMQService = new RabbitMQService(
    'queue',
    'route-key',
    '',
    '',
    '',
    false,
    true,
    false,
    false,
    [
        'x-message-ttl'             => ['I', 15000],                    // TTL in ms (integer)
        'x-dead-letter-exchange'    => ['S', ''],                       // dead-letter exchange (string)
        'x-dead-letter-routing-key' => ['S', 'your-queue-name.dlq'],    // DLQ routing key (string)
        'x-delivery-limit'          => ['I', 3],                        // retry 3x, on 4th goes to DLQ
    ]
);

$rabbitMQService->consume(function ($msg) {
    // your code here
    $msg->ack();
});

$rabbitMQService->destruct();
```

Using `QueueBuilder`:

```php
<?php

use Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq\QueueBuilder;

$queue = (new QueueBuilder())
    ->setQueueName('queue')
    ->setRouteKey('route-key')
    ->setIsDurable(true)
    ->setArguments([
        'x-message-ttl'             => ['I', 15000],
        'x-dead-letter-exchange'    => ['S', ''],
        'x-dead-letter-routing-key' => ['S', 'your-queue-name.dlq'],
        'x-delivery-limit'          => ['I', 3],
    ])
    ->getQueue();

$queue->consume(function ($msg) {
    // your code here
    $msg->ack();
});

$queue->destruct();
```

Using `QueueProducer`:

```php
<?php

use Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq\QueueProducer;

// Inject QueueProducer via constructor or resolve from container
$queueProducer->produce('your-queue-name', $payload, [
    'x-message-ttl'             => ['I', 15000],
    'x-dead-letter-exchange'    => ['S', ''],
    'x-dead-letter-routing-key' => ['S', 'your-queue-name.dlq'],
    'x-delivery-limit'          => ['I', 3],
]);

// Batch publish
$queueProducer->produceBatch('your-queue-name', $messages, [
    'x-message-ttl'             => ['I', 15000],
    'x-dead-letter-exchange'    => ['S', ''],
    'x-dead-letter-routing-key' => ['S', 'your-queue-name.dlq'],
    'x-delivery-limit'          => ['I', 3],
]);

// Publish with AMQP application headers
$queueProducer->produceWithHeaders(
    'dasa_priority_low',
    ['fiad_id' => 123],
    ['message_type' => 'dasa_update_pbzpa']
);

// Batch publish with AMQP application headers
$queueProducer->produceBatchWithHeaders(
    'dasa_priority_low',
    [
        ['fiad_id' => 1],
        ['fiad_id' => 2],
    ],
    ['message_type' => 'dasa_update_pbzpa']
);
```

> **Note:** The `$arguments` parameter in `QueueProducer` is optional. When provided, they must match the arguments used by the consumer to avoid `PRECONDITION_FAILED` errors.
>
> `arguments` configure the queue itself (`x-message-ttl`, DLQ, quorum, etc.). `headers` configure the message being published (`message_type`, tenant, trace metadata, etc.). They solve different problems and should not be mixed.

# Remote procedure call (RPC)
## Configuration for RPC
For publishing and consuming with RPC, please ensure that the RabbitMQ class is configured with 'durable = false' and 'auto_delete = true' settings.


When setting up Remote Procedure Call (RPC) functionality, it's crucial to configure RabbitMQ appropriately to ensure seamless communication. For optimal performance and resource management, it's recommended to adjust the RabbitMQ settings with 'durable = false' and 'auto_delete = true'.

### Creating a Consumer for RPC
```php
<?php

use Edipoelwes\LaravelRabbitmqWorker\Services\RabbitMQ\RabbitMQService;

$rabbitMQService = new RabbitMQService(
    'rpc_queue',       // Queue
    'rpc_queue',       // Routing key
    '',                // Exchange
    '',                // Exchange Type
    'rpc_queue',       // Consumer Tag
    false,             // Passive
    false,             // Durable
    false,             // Exclusive
    true               // Auto delete
);

// Define RPC Consumer Callback
$rabbitMQService->consume(function ($req) {
    
    // Your code here
    
    // Prepare response payload
    $payload = "your message";

    // Publish response to the specified channel and correlation ID
    $msg = new AMQPMessage(
        $payload,
        array('correlation_id' => $req->get('correlation_id'))
    );

    $req->getChannel()->basic_publish(
        $msg,
        '',
        $req->get('reply_to')
    );

    $req->ack(); // Acknowledge message processing
});

$rabbitMQService->destruct(); // Clean up resources

```

### Creating a Publisher for RPC

```php

<?php

// Configure RabbitMQ for RPC Publisher
$rabbitMQService = new RabbitMQService(
    'rpc_queue',       // Queue
    'rpc_queue',       // Routing key
    '',                // Exchange
    '',                // Exchange Type
    'rpc_queue',       // Consumer Tag
    false,             // Passive
    false,             // Durable
    false,             // Exclusive
    true               // Auto delete
);

// Prepare response payload
$payload = "your message";

$response = $rabbitMQService->publishRpc($payload);

// your code here

$rabbitMQService->destruct(); // Clean up resources
```

## Priority queues (high/default/low)

Em vez de uma fila dedicada por ação, consolide o consumo em 3 filas físicas por
prioridade. Cada mensagem carrega o header AMQP `message_type`, e o
`PriorityMessageRouter` resolve o consumer da aplicação em
`config('laravel-rabbitmq-worker.priority.routes')`.

### 1. Configure prefixo, filas e rotas (config publicado da aplicação)

```php
// config/laravel-rabbitmq-worker.php
// ATENÇÃO: o mergeConfigFrom é raso — defina o bloco `priority` completo.
'command_prefix' => env('RABBITMQ_COMMAND_PREFIX', 'meuapp'),

'priority' => [
    'queues' => [
        'high'    => env('RABBITMQ_PRIORITY_QUEUE_HIGH', 'meuapp_priority_high'),
        'default' => env('RABBITMQ_PRIORITY_QUEUE_DEFAULT', 'meuapp_priority_default'),
        'low'     => env('RABBITMQ_PRIORITY_QUEUE_LOW', 'meuapp_priority_low'),
    ],
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
    'routes' => [
        'meu_message_type' => [
            'priority' => 'default',
            'consumer' => \App\Consumers\MeuConsumer::class, // classe com process($message)
        ],
    ],
],
```

E no `.env`:

```
RABBITMQ_COMMAND_PREFIX=meuapp
```

### 2. Os workers de fila já vêm prontos na lib

A lib registra nativamente 3 commands Artisan de consumo — um por prioridade —
usando `PriorityQueueConsumerAbstract` internamente. O nome final de cada
command respeita o `command_prefix` configurado:

- `<command_prefix>_priority_high`
- `<command_prefix>_priority_default`
- `<command_prefix>_priority_low`

Com `RABBITMQ_COMMAND_PREFIX=meuapp`, isso vira `meuapp_priority_high`,
`meuapp_priority_default` e `meuapp_priority_low` — nomes que você usa
diretamente em `RABBITMQ_LARAVEL_WORKERS` / `php artisan rabbitmq:run`. Não é
necessário criar nenhuma subclasse na aplicação para isso.

Se precisar de um worker com nome fora desse padrão (caso raro), ainda é
possível estender `PriorityQueueConsumerAbstract` diretamente e definir
`$signature` manualmente:

```php
use Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq\PriorityQueueConsumerAbstract;

class PriorityDefaultCommand extends PriorityQueueConsumerAbstract
{
    protected $signature = 'nome-fora-do-padrao';
    protected string $priority = 'default';
}
```

### 3. Crie o consumer da ação (PriorityConsumerInterface)

```php
use Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq\PriorityConsumerInterface;

class MeuConsumer implements PriorityConsumerInterface
{
    public function process($message): void
    {
        $data = json_decode($message->body, true);
        // regra de negócio; o consumer decide ack/nack/reject
        $message->ack();
    }
}
```

### 4. Publique com o header de roteamento

Forma recomendada — a prioridade é inferida automaticamente de
`priority.routes.<message_type>.priority`, então você não repete
`'high'|'default'|'low'` no código da aplicação:

```php
use Edipoelwes\LaravelRabbitmqWorker\Infrastructure\Queue\Rabbitmq\QueueProducer;

app(QueueProducer::class)->produceRouted('meu_message_type', ['id' => 123]);
app(QueueProducer::class)->produceRoutedBatch('meu_message_type', $payloads);
```

`produceRouted()`/`produceRoutedBatch()` lançam `InvalidArgumentException` se
`meu_message_type` não estiver mapeado em `priority.routes` (ou a rota não
definir `priority`) — isso é intencional: preferimos falhar rápido a publicar
silenciosamente em `default`.

`producePriority()`/`producePriorityBatch()` continuam existindo e funcionando
exatamente como antes — use-as quando precisar de um **override explícito** da
prioridade (ex.: um job que decide a prioridade em tempo de execução, fora do
que está mapeado em `priority.routes`):

```php
app(QueueProducer::class)->producePriority('low', 'meu_message_type', ['id' => 123]);
app(QueueProducer::class)->producePriorityBatch('low', 'meu_message_type', $payloads);
```

### 4.1. Publicando no namespace de outro sistema (`remotes`)

Quando dois sistemas compartilham o mesmo broker e cada um é dono das suas
filas de prioridade (ex.: `dasa_priority_*` consumidas pelo DASA e
`eco-utm_priority_*` consumidas pelo Eco UTM), o produtor publica nas filas do
**outro** sistema declarando a topologia dele em `priority.remotes`:

```php
'priority' => [
    // topologia LOCAL: as filas que ESTE app consome
    'queues' => [
        'high' => 'eco-utm_priority_high',
        'default' => 'eco-utm_priority_default',
        'low' => 'eco-utm_priority_low',
    ],

    // topologias REMOTAS: filas de outros sistemas, só para publicação.
    // queues e dead_letter DEVEM espelhar a config do sistema remoto,
    // senão o queue_declare do produtor gera PRECONDITION_FAILED.
    'remotes' => [
        'dasa' => [
            'queues' => [
                'high' => 'dasa_priority_high',
                'default' => 'dasa_priority_default',
                'low' => 'dasa_priority_low',
            ],
            'dead_letter' => [
                'enabled' => true,
                'suffix' => '.dlq',
                'delivery_limit' => 3,
                'queue_type' => 'quorum',
            ],
        ],
    ],

    'routes' => [
        // outbound: publica na fila do DASA; sem 'consumer' (quem processa é o DASA)
        'eco-utm_flight-requests' => ['priority' => 'high', 'remote' => 'dasa'],

        // inbound/interno: fila local, com consumer deste app
        'minha_rotina_interna' => ['priority' => 'low', 'consumer' => MinhaRotina::class],
    ],
],
```

`produceRouted()`/`produceRoutedBatch()` inferem o `remote` da rota — o código
da aplicação não muda. Para override explícito, `producePriority()` e
`producePriorityBatch()` aceitam o namespace como quinto parâmetro:

```php
app(QueueProducer::class)->produceRouted('eco-utm_flight-requests', ['id' => 123]); // vai para dasa_priority_high
app(QueueProducer::class)->producePriority('high', 'meu_message_type', $payload, [], 'dasa');
```

O **consumo é sempre local**: os commands `<prefix>_priority_*` só consomem
`priority.queues`, e a criação/consumo das DLQs de um namespace remoto é
responsabilidade do sistema dono das filas. Uma rota com `remote` aponta para
um nome inexistente em `priority.remotes`? `InvalidArgumentException` — falha
rápido em vez de publicar na fila errada.

### 5. Teste manual

`rabbitmq:priority-publish` usa a mesma inferência por padrão. Passe
`--priority` só quando quiser sobrescrever a rota (ou testar um `message_type`
ainda não mapeado):

```bash
php artisan rabbitmq:priority-publish meu_message_type --payload='{"id":123}'
php artisan rabbitmq:priority-publish meu_message_type --priority=low --count=5
```

Mensagens sem header `message_type` ou com tipo não mapeado são rejeitadas sem
requeue (`reject(false)`) e logadas — a fila nunca trava por mensagem
desconhecida. Lembre-se: workers são processos de longa duração; após alterar
`priority.routes`, reinicie os workers.

### 6. DLQ por fila de prioridade (evita loop infinito)

Cada fila física de prioridade (`priority_high`, `priority_default`,
`priority_low`) ganha automaticamente uma DLQ irmã (`<fila>.dlq` por padrão).
A DLQ é por **fila de prioridade**, não por `message_type` — isso mantém o
modelo simples (3 filas + 3 DLQs) mesmo que o app tenha dezenas de rotas em
`priority.routes`.

Isso é resolvido de forma centralizada por `PriorityQueueTopology` e usado
tanto por `PriorityQueueConsumerAbstract` (declara a fila principal com os
argumentos de DLQ e garante que a `.dlq` exista) quanto por
`QueueProducer::producePriority()`/`producePriorityBatch()` — e por extensão
`produceRouted()`/`produceRoutedBatch()`, que delegam para elas — aplicando os
mesmos argumentos ao publicar, evitando `PRECONDITION_FAILED` por divergência
de argumentos entre quem publica e quem consome.

Com `dead_letter.enabled` (padrão `true`), a fila principal é declarada com:

```
x-dead-letter-exchange     = ''
x-dead-letter-routing-key  = '<fila>.dlq'
x-delivery-limit           = dead_letter.delivery_limit (padrão 3)
```

Fluxo resultante dentro do `process($message)` do seu consumer:

- `$message->ack()` — mensagem concluída, some da fila.
- `$message->reject(false)` — Rabbit manda direto para a `.dlq`, sem
  reentrega. Use quando o erro é claramente não recuperável (payload
  inválido, `message_type` desconhecido).
- `$message->nack(true)` — Rabbit reentrega a mensagem na mesma fila. Use
  para falhas transitórias (timeout, serviço externo fora do ar). A cada
  reentrega o contador de tentativas da fila quorum sobe; ao ultrapassar
  `x-delivery-limit`, o **próprio Rabbit** manda a mensagem para a `.dlq`
  automaticamente — não é preciso contar tentativas manualmente no código do
  consumer.

Isso é o que evita o loop infinito de `nack(true)`: sem `x-delivery-limit`, uma
mensagem "envenenada" fica sendo reentregue para sempre e trava o
processamento das mensagens atrás dela na fila; com o limite configurado, ela
sai do caminho principal após N tentativas e vai para a `.dlq`, onde pode ser
inspecionada/reprocessada manualmente sem impactar a fila principal.

Para desabilitar (não recomendado em produção) ou customizar por prioridade:

```php
'dead_letter' => [
    'enabled' => true,
    'suffix' => '.dlq',
    'delivery_limit' => 3,
    'queue_type' => 'quorum', // tipo da fila .dlq em si
    'priorities' => [
        'high' => [],                        // usa os valores globais acima
        'default' => ['delivery_limit' => 5], // sobrescreve só o delivery_limit
        'low' => ['enabled' => false],        // desliga DLQ só para low
    ],
],
```

> **Retry com atraso (TTL) é outro mecanismo, separado da DLQ.** O padrão
> `.retry` usado hoje no Eco UTM (ex.: `UpdateFlightCommand`, fila `.retry`
> com `x-message-ttl` para reentregar depois de um tempo) resolve um problema
> diferente — espaçar retentativas no tempo — e **não** faz parte desta
> entrega. A DLQ aqui só garante que mensagens esgotadas ou irrecuperáveis
> não travem a fila principal; um mecanismo de retry com TTL genérico pode
> ser adicionado depois como uma segunda etapa, sem alterar o que já existe.
