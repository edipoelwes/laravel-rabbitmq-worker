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
```

> **Note:** The `$arguments` parameter in `QueueProducer` is optional. When provided, they must match the arguments used by the consumer to avoid `PRECONDITION_FAILED` errors.

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
