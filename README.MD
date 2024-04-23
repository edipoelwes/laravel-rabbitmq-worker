# laravel-rabbitmq-worker
## Library to facilitate the use of rabbitmq within php based on the php-amqplib library.

##
# Installing

```
composer require edipoelwes/laravel-rabbitmq-worker
```

## How to configure in Laravel
#### Run the publisher and it will create the file in config/laravel-rabbitmq-worker.php
```
php artisan vendor:publish --provider="Edipoelwes\LaravelRabbitmqWorker\CommandServiceProvider"
```

## Clear settings cache
```
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

$rabbitMQService = new RabbitMQService('queue', 'route-key');
$rabbitMQService->publish('Your menssage');
$rabbitMQService->destruct();
```

### Creating a consumer

```php

<?php

use Edipoelwes\LaravelRabbitmqWorker\Services\RabbitMQ\RabbitMQService;

$rabbitMQService = new RabbitMQService('queue', 'route-key');

$callback = function ($msg) {
    
    // your code here
    
    $msg->ack();
}

$rabbitMQService->consume($callback);

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
        $rabbitMQService = new RabbitMQService('queue', 'route-key');

        $callback = function ($msg) {
            
            // your code here
            
            $msg->ack();
        }
        
        $rabbitMQService->consume($callback);
    }
}

```
