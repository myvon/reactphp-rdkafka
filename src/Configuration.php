<?php

namespace Myvon\Kafka;

use RdKafka\Conf;
use RdKafka\KafkaConsumer;
class Configuration
{
    protected array $nodes = [];
    private string $groupId;
    private array $customKafkaConf;
    private ?\Closure $rebalanceCb = null;

    public function __construct(string $groupId = "app", array $nodes = ["127.0.0.1:9092"], array $customKafkaConf = []) {
        $this->nodes = $nodes;
        $this->groupId = $groupId;
        $this->customKafkaConf = $customKafkaConf;
    }

    public function setRebalanceCb(\Closure $callback) {
        $this->rebalanceCb = $callback;

        return $this;
    }
    public function setGroupId(string $groupId) {
        $this->groupId = $groupId;

        return $this;
    }

    public function addNode(string $node) {
        $this->nodes[] = $node;
    }

    public function consumer() {
        $conf = new Conf();
        $conf->set('metadata.broker.list', implode(',', $this->nodes));
        $conf->set('group.id', $this->groupId);
        if(null !== $this->rebalanceCb) {
            $conf->setRebalanceCb($this->rebalanceCb);
        }

        foreach($this->customKafkaConf as $key => $value) {
            $conf->set($key, $value);
        }

        if(!isset($this->customKafkaConf['enable.partition.eof'])) {
            $conf->set('enable.partition.eof', 'true');
        }
        if(!isset($this->customKafkaConf['auto.offset.reset'])) {
            $conf->set('auto.offset.reset', 'earliest');
        }

        // We allow the user to override this parameter but be advised that it can lead to lose the non-blocking aspect
        if(!isset($this->customKafkaConf['socket.blocking.max.ms'])) {
            $conf->set('socket.blocking.max.ms', 1);
        }

        return $conf;
    }

    public function producer() {
        $conf = new Conf();
        $conf->set('metadata.broker.list', implode(',', $this->nodes));

        foreach($this->customKafkaConf as $key => $value) {
            $conf->set($key, $value);
        }


        return $conf;

    }
}