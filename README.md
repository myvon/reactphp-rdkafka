# reactphp-rdkafka
RdKafka implementation with ReactPHP EventLoop.

This library implement [PHP RDKafka](https://github.com/arnaud-lb/php-rdkafka) from [Arnaud-lb](https://github.com/arnaud-lb/) with [react/event-loop](https://github.com/reactphp/event-loop) and [react/stream](https://github.com/reactphp/stream) and provide a non-blocking event-driven Consumer and Producer.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/myvon/reactphp-rdkafka.svg?style=flat-square)](https://packagist.org/packages/myvon/reactphp-rdkafka)
[![Tests](https://github.com/myvon/reactphp-rdkafka/actions/workflows/run-test.yml/badge.svg)](https://github.com/myvon/reactphp-rdkafka/actions/workflows/run-test.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/myvon/reactphp-rdkafka.svg?style=flat-square)](https://packagist.org/packagesmyvon/reactphp-rdkafka)

# How it works

This package use periodic timers from [react/event-loop](https://github.com/reactphp/event-loop) to consume messages at regular interval.
To avoid blocking, timeout is set to 0 when consuming. 

It also use [react/stream](https://github.com/reactphp/stream) to receive and send message in an event-driven way.
Consuming message is done by listening to the `data` event of the steam. 
Producing message is done by writing data to the corresponding stream. See `Consuming Messages` and `Producing Messages` below.

# Installation

You can install the package via composer: 
```bash
composer require myvon/reactphp-kafka
```
Be sure to have the [PHP RDKafka](https://github.com/arnaud-lb/php-rdkafka) extension installed on your server.

# Consuming Messages

To consume message start by creating a `Configuration` object by passing it the name of your application (used for `group.id` configuration of kafka, see [Consumer group id (general)](https://github.com/arnaud-lb/php-rdkafka#consumer-group-id-general) for more information) and the list of your brokers : 
```php
use Myvon\Kafka\Configuration;

$configuration = new Configuration("appName", ["127.0.0.1:9092"]);
```

Then, you can create an "Myvon\Kafka\Consumer" instance:
```php
use Myvon\Kafka\Consumer;

$consumer = new Consumer($configuration->consumer());
```
Using `Configuration::consumer()` generate an `RdKafka\Conf` instance with correct configuration for a Consumer.

You can then start consuming message by calling the `start` method of the consumer and passing it the list of topics you want to subscribe : 
```php
$stream = $consumer->start(['topic']);
```

This method will return an `ThroughStream` instance, allowing you to listen to the `data` event to receive messages : 
```php
use Myvon\Kafka\Configuration;
use Myvon\Kafka\Consumer;

$configuration = new Configuration("appName", ["127.0.0.1:9092"]);
$consumer = new Consumer($configuration->consumer());
$stream = $consumer->start(['topic']);

$stream->on('data', function($data) {
    $topic = $data['topic'];
    $message = $data['payload'];
    
    //... do whatever you want here 
});
```

The `$data` parameter will contain the following keys : 
- `topic`: contain the name of the topic the message come from
- `payload` : contain the message received

# Handling consumer errors

The consumer will write to the stream every message received with error `RD_KAFKA_RESP_ERR_NO_ERROR`. 

`RD_KAFKA_RESP_ERR__TIMED_OUT` and `RD_KAFKA_RESP_ERR__PARTITION_EOF` will be ignored. 

Every other error will be sent through the `error` event : 
```php

$stream->on("error", function(Exception $exception) {
    $errorStr = $exception->getMessage();
    $errorCode = $exception->getCode();
    // handle the error here
});
```
This package does not handle errors, it simply pass it to your application. It's up to you to handle it.

# Consumer timeout and periodic timer

By default the timeout passed to the `consume` method of `RdKafka\KafkaConsumer` it set to 0. This prevents the method to block the execution of the script. If you want to set a timeout anyway, you can do it by passing the desired timeout (in ms) to the `setConsumeTimeout` method : 
```php
$consumer->setConsumeTimeout(1000); // 1 second
```
Be aware that this will affect the EventLoop !

BY default the consumer will look for messages every 1 second. You can set this timer by passing the new timer to the `setTimerPeriod` method :
```php
$consumer->setTimerPeriod(0.1); // 100 ms
```
Notice: It internally use the `addPeriodicTimer` method of the EventLoop so the timer is in second.

# Accessing the KafkaConsumer instance

If you need to access the KafkaConsumer instance directly, you can do it by calling `getConsumer`:
```php
$kafkaConsumer = $consumer->getConsumer();
```

# Producing Messages

Like the Consumer, you need to create the configuration object and pass it to `Myvon\Kafka\Producer` when instantiating it : 
```php
use Myvon\Kafka\Configuration;
use Myvon\Kafka\Producer;

$configuration = new Configuration("appName", ["127.0.0.1:9092"]);
$producer = new Producer($configuration->producer());
$streams = $producer->start(['topicName']);
```
You can pass multiple topic to the `start` method. Call `start` will return one instance of `ThroughStream` by topic you want to publish in.
You can access the stream of a given topic by :
- Accessing it through the data returned by the `start` method : `$streams['topicName']`
- Retrieving the `ThroughStream` instance of a given topic by calling `getStream('topicName')`

You can then write message to the stream, which will by produced to the corresponding topic : 
```php
use Myvon\Kafka\Configuration;
use Myvon\Kafka\Producer;

$configuration = new Configuration("appName", ["127.0.0.1:9092"]);
$producer = new Producer($configuration->producer());
$streams = $producer->start(['myFirstTopic', 'mySecondTopic']);

$streams['myFirstTopic']->write('Hello First Topic !');
$streams['mySecondTopic']->write('Hello Second Topic !');

$producer->getStream('myFirstTopic')->write('Hello sent with getStream !');
```
Notice:  the producer will ensure every message is sent by calling `poll()` every 500ms. This delay can be changed by calling `setPollInterval`. When streams are closed, the producer `poll` every message and `flush` them to be sure noting is lost.

# I don't want to use the EventLoop when producing message

Sometime, you want to produce message directly without using an eventloop. This can be done by passing false as second argument to the `start` method : 
```php
use Myvon\Kafka\Configuration;
use Myvon\Kafka\Producer;

$configuration = new Configuration("appName", ["127.0.0.1:9092"]);
$producer = new Producer($configuration->producer());
$streams = $producer->start(['myFirstTopic', 'mySecondTopic'], false);

$streams['myFirstTopic']->write('Hello First Topic !');
$streams['mySecondTopic']->write('Hello Second Topic !');

$producer->getStream('myFirstTopic')->write('Hello sent with getStream !');
```
This will deactivate every usage of the loop in the producer and your code will immediately exit after the last line.
To avoid loosing message, streams are closed on the destruction of the class, thanks to the `__destruct()` method. 

# Gracefully stopping Consumer and Producer
If you want to stop the Consumer or the Producer before your code end, you can call the `stop()` method of each class. This will remove every periodic timer and close all streams.

By doing this you will receive the `end` and `close` events of every stream used by the Consumer or the Producer.

# Using custom configuration option
You can pass custom configuration option to the `RdKafka\Conf` instance generated by passing them as an array to the thrid arguments of the `Myvon\Kafka\Configuration` constructor : 
```php
$configuration = new Configuration('appName', ['127.0.0.1:9092'], ['enable.partition.eof' => 'false']);
```
# Using a custom loop

If you don't want the Consumer or the Producer to use the default loop, you can pass it as second arguments of the constructor of each class : 
```php
$loop = new AnotherLoopInstance();
$producer = new Producer($configuration->producer(), $loop);
$consumer = new Consumer($configuration->consumer(), $loop);
```
Notice: passing a loop to the producer will force loop utilization even if you pass `false` as second argument of `start`

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
