<?php

beforeEach(function() {
    $this->loop = new \React\EventLoop\StreamSelectLoop();

    $this->producer =  new \Myvon\Kafka\Producer((new \Myvon\Kafka\Configuration())->producer(), $this->loop);
});


test('Test that the producer use one stream per topic', function() {
    $topics = ['test1', 'test2', 'test3'];

    $messages = [];

    foreach($topics as $topic) {
        $stream = $this->producer->getStream($topic);
        $stream->on("data", function($data) use(&$messages, $stream, $topic) {
            $messages[$topic] = $data;
            $stream->end();
        });
        $this->loop->futureTick(function() use($stream, $topic) {
            $stream->write($topic);
        });
    }

    $this->loop->run();

    expect($messages)->toBeArray()
        ->toHaveCount(count($topics));
});

test('Test that the producer can gracefully close', function() {
    $topics = ['test1', 'test2', 'test3'];

    $mockKafkaProducerTopic = mock(\RdKafka\ProducerTopic::class)->expect();

    $mockKafkaProducer = mock(\RdKafka\Producer::class)->expect(
        newTopic: fn($topics) => $mockKafkaProducerTopic,
        getOutQLen: fn() => 0,
        poll: fn($i) => true,
        flush: fn($ms) => true,
    );

    $reflection = new ReflectionProperty($this->producer, 'producer')   ;
    $reflection->setValue($this->producer, $mockKafkaProducer);

    $streams = $this->producer->start($topics);
    $this->loop->addTimer(1, function() {
        $this->producer->stop();
    });

    $this->loop->run();

    expect($streams)->toBeArray();
});


test('Test that the producer can produce', function() {
    $topics = ['test1', 'test2', 'test3'];

    $mockKafkaProducerTopics = [];
    foreach($topics as $topic) {
        $mockKafkaProducerTopics[$topic] = mock(\RdKafka\ProducerTopic::class)->shouldReceive("produce")->withArgs([RD_KAFKA_PARTITION_UA, 0, $topic])->andReturnSelf()->getMock();
    }

    $mockKafkaProducer = mock(\RdKafka\Producer::class)->expect(
        newTopic: fn($topicI) => $mockKafkaProducerTopics[$topicI],
        getOutQLen: fn() => 0,
        poll: fn($i) => true,
        flush: fn($ms) => true,
    );

    $reflection = new ReflectionProperty($this->producer, 'producer');
    $reflection->setValue($this->producer, $mockKafkaProducer);

    $streams = $this->producer->start($topics);
    foreach($streams as $topic => $stream) {
        $stream->write($topic);
    }

    $this->loop->addTimer(2, function() {
        $this->producer->stop();
    });

    $this->loop->run();

    expect($streams)->toBeArray();
});

