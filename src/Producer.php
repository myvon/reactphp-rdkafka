<?php

namespace Myvon\Kafka;

use RdKafka\Conf;
use RdKafka\ProducerTopic;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Stream\ThroughStream;

class Producer
{
    private Conf $conf;
    private ?LoopInterface $loop;
    private ?\RdKafka\Producer $producer = null;

    /** @var TimerInterface[] */
    private array $timers = [];

    /** @var ThroughStream[] */
    private array $streams = [];

    /** @var ProducerTopic[] */
    private array $topics = [];

    private int $flushTimeout = 1000;

    /**
     * Time between poll()
     * @var float
     */
    private float $pollInterval = 0.5;

    public function __construct(Conf $conf, ?LoopInterface $loop = null)
    {
        $this->conf = $conf;
        $this->loop = $loop;
    }

    /**
     * Set timer between each poll()
     * @param float $pollInterval
     * @return $this
     */
    public function setPollInterval(float $pollInterval): self
    {
        $this->pollInterval = $pollInterval;
        return $this;
    }

    /**
     * Set the timeout in ms for the flush() method of RdKafka\Producer
     * @param int $flushTimeout
     */
    public function setFlushTimeout(int $flushTimeout): void
    {
        $this->flushTimeout = $flushTimeout;
    }

    /**
     * Start the producer for the givens topics and return an array with streams associated to corresponding topics:
     * [
     *  "topic1" => ThroughStream for topic1,
     *  "topic2" => ThroughStream for topic2,
     *   ...
     * ]
     *
     * @param string[] $topics
     * @return ThroughStream[]
     */
    public function start(array $topics, bool $useLoop = true): array
    {
        if($useLoop && $this->loop === null) {
            $this->loop = Loop::get();
        }
        foreach($topics as $topic) {
            if(!isset($this->topics[$topic])) {
                $this->topics[$topic] = $this->getProducer()->newTopic($topic);

                // Produce messages when written in stream
                $this->getStream($topic)->on("data", function ($payload) use ($topic) {
                    // TODO : add a callback to make a custom produce() call, something like a closure to which we send the ProducerTopic instance and the payload and the closure do the call with custom args
                    $this->topics[$topic]->produce(RD_KAFKA_PARTITION_UA, 0, $payload);
                });

                // On stream close, gracefully shutdown producer
                $this->getStream($topic)->on("close", function() {
                    // Poll every remaining messages
                    while ($this->getProducer()->getOutQLen() > 0) {
                        $this->getProducer()->poll(1);
                    }
                    $this->getProducer()->flush($this->flushTimeout);
                });

                if($this->loop !== null) {
                    // Automatically poll every $this->pollInterval seconds
                    $this->timers[] = $this->loop->addPeriodicTimer($this->pollInterval, function () {
                        $this->getProducer()->poll(1);
                    });
                }
            }
        }

        return array_combine($topics, array_map(function($topic) {
            return $this->getStream($topic);
        }, $topics));
    }

    /**
     * Gracefully stop the producer and all stream / timers
     */
    public function stop()
    {
        foreach($this->timers as $timer) {
            $this->loop->cancelTimer($timer);
        }

        foreach($this->streams as $stream) {
            $stream->end();
        }
    }

    /**
     * Return the RdKafka\Producer instance, instantiate it if not done yet
     * @return \RdKafka\Producer
     */
    public function getProducer(): \RdKafka\Producer
    {
        if(null === $this->producer) {
            $this->producer = new \RdKafka\Producer($this->conf);
        }

        return $this->producer;
    }


    /**
     * Return a stream for the given topic
     * @param string $topic
     * @return ThroughStream
     */
    public function getStream(string $topic): ThroughStream
    {
        if(!isset($this->streams[$topic])) {
            $this->streams[$topic] = new ThroughStream();
        }
        return $this->streams[$topic];
    }
}