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
