<?php

test("Test that default consumer configuration is applied", function() {
    $conf = new \Myvon\Kafka\Configuration("app", ['1.2.3.4:9092']);

    $conf = $conf->consumer()->dump();
    expect($conf)->toBeArray()
        ->toHaveKeys(['group.id', 'metadata.broker.list'])
        ->toHaveKey("enable.partition.eof", "true");
});

test("Test that custom consumer configuration is applied", function() {
    $conf = new \Myvon\Kafka\Configuration("test", ['1.2.3.4:9092'], ['enable.partition.eof' => 'false']);

    $conf = $conf->consumer()->dump();
    expect($conf)->toBeArray()
        ->toHaveKeys(['group.id', 'metadata.broker.list'])
        ->toHaveKey("enable.partition.eof", "false")
        ->toHaveKey("group.id", "test");
});

test("Test that default producer configuration is applied", function() {
    $conf = new \Myvon\Kafka\Configuration("app", ['1.2.3.4:9092']);

    $conf = $conf->producer()->dump();
    expect($conf)->toBeArray()
        ->not()->toHaveKeys(['group.id']);
});

test("Test that custom producer configuration is applied", function() {
    $conf = new \Myvon\Kafka\Configuration("test", ['1.2.3.4:9092'], ['enable.partition.eof' => 'false']);

    $conf = $conf->producer()->dump();
    expect($conf)->toBeArray()
        ->toHaveKey("enable.partition.eof", "false")
        ->not()->toHaveKeys(['group.id']);
});