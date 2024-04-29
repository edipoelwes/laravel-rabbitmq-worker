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
    false          // Auto delete
);

$callback = function ($msg) {
    //  $msg->body
    // your code here
    
    $msg->ack();
}

$rabbitMQService->consume($callback);
$rabbitMQService->destruct(); // Clean up resources

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
            false        // Auto delete
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
