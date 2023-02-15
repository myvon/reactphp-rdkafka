<?php

beforeEach(function() {
    $this->loop = new \React\EventLoop\StreamSelectLoop();
    $this->consumer = new \Myvon\Kafka\Consumer((new \Myvon\Kafka\Configuration())->consumer(), $this->loop);
});

test("Test that consumer can consume", function() {
    $message = new \RdKafka\Message();
    $message->err = RD_KAFKA_RESP_ERR_NO_ERROR;
    $message->payload = "test";
    $message->topic_name = "testTopic";

    $mock = mock(\RdKafka\KafkaConsumer::class);
    $mock->shouldReceive("subscribe")->withArgs([['testTopic']])->andReturnSelf();
    $mock = $mock->shouldReceive("consume")->withArgs([0])->andReturn($message)->getMock();


    $reflection = new ReflectionProperty($this->consumer, 'consumer');
    $reflection->setAccessible(true);
    $reflection->setValue($this->consumer, $mock);

    $stream = $this->consumer->getStream();

    $receivedData = "";
    $stream->on("data", function($data) use(&$receivedData) {
        $receivedData = $data;
        $this->consumer->stop();
    });

    $this->consumer->start(['testTopic']);

    $this->loop->run();

    expect($receivedData)->toEqual(['topic' => 'testTopic', 'payload' => "test"]);
});

test("Test that ending consumer trigger end and close event from stream", function() {
    $stream = $this->consumer->getStream();

    $stream->on("data", function($data) {
        $this->consumer->stop();
    });

    $called = 0;
    $stream->on('end', function() use(&$called) {
        $called++;
    });
    $stream->on('close', function() use(&$called) {
        $called++;
    });

    $this->loop->futureTick(function() use($stream) {
        $stream->write("test");
    });

    $this->loop->run();

    expect($called)->toEqual(2);
});

test("Test that error on consumer works", function() {
    $message = new \RdKafka\Message();
    $message->err =  RD_KAFKA_RESP_ERR__ALL_BROKERS_DOWN;
    $message->payload = "";

    $mock = mock(\RdKafka\KafkaConsumer::class);
    $mock->shouldReceive("subscribe")->withArgs([['testTopic']])->andReturnSelf();
    $mock = $mock->shouldReceive("consume")->withArgs([0])->andReturn($message)->getMock();


    $reflection = new ReflectionProperty($this->consumer, 'consumer');
    $reflection->setAccessible(true);
    $reflection->setValue($this->consumer, $mock);

    $stream = $this->consumer->getStream();

    /** @var Exception|null $error */
    $error = null;
    $stream->on("error", function($data) use(&$error) {
        $error = $data;
        $this->consumer->stop();
    });

    $this->consumer->start(['testTopic']);
    $this->loop->run();

    expect($error)->toBeInstanceOf(Exception::class)
        ->and($error->getMessage())->toEqual($message->errstr())
        ->and($error->getCode())->toEqual($message->err);
});