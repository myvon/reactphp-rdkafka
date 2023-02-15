<?php

namespace Myvon\Kafka;

use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Stream\ThroughStream;

class Consumer
{
    private Conf $conf;
    private ?KafkaConsumer $consumer = null;
    private ?TimerInterface $timer = null;
    private LoopInterface $loop;
    private ?ThroughStream $stream = null;

    /**
     * Timeout of kafka's consume()
     * We let the user the possibility to change set a timeout but be advised that it will lose the non-blocking aspect
     * @var int
     */
    private int $consumeTimeout = 0;

    /**
     * Time between two consume
     * @var float
     */
    private float $timerPeriod = 1;

    public function __construct(Conf $conf, ?LoopInterface $loop = null)
    {
        $this->conf = $conf;
        if($loop === null) {
            $loop = Loop::get();
        }

        $this->loop = $loop;
    }

    /**
     * Return the KafkaConsumer instance, instantiate it if not done yet
     * @return KafkaConsumer
     */
    public function getConsumer(): KafkaConsumer
    {
        if($this->consumer === null) {
            $this->consumer = new KafkaConsumer($this->conf);
        }

        return $this->consumer;
    }

    /**
     * Set kafka's consume() timeout
     * @param int $timeout
     * @return $this
     */
    public function setConsumeTimeout(int $timeout): self
    {
        $this->consumeTimeout = $timeout;
        return $this;
    }

    /**
     * Set time between two consume() call
     * @param float $timerPeriod
     * @return $this
     */
    public function setTimerPeriod(float $timerPeriod): Consumer
    {
        $this->timerPeriod = $timerPeriod;
        return $this;
    }

    /**
     * Gracefully stop consumer, stream and timer
     * @return void
     */
    public function stop()
    {
        if(null !== $this->timer) {
            $this->loop->cancelTimer($this->timer);
        }

        if(null !== $this->stream) {
            $this->stream->end();
        }
    }

    /**
     * Get consumer stream
     * @return ThroughStream
     */
    public function getStream(): ThroughStream
    {
        if(null === $this->stream) {
            $this->stream = new ThroughStream();
        }

        return $this->stream;
    }

    /**
     * Start consumer and return the stream
     *
     * @param string[] $topics
     * @return ThroughStream
     */
    public function start(array $topics)
    {
        $this->loop->futureTick(function() use($topics) {
            $this->getConsumer()->subscribe($topics);
        });

        $this->timer = $this->loop->addPeriodicTimer($this->timerPeriod, function() {
            $message = $this->getConsumer()->consume($this->consumeTimeout);
            switch ($message->err) {
                case RD_KAFKA_RESP_ERR_NO_ERROR:
                    $this->getStream()->write(['topic' => $message->topic_name, 'payload' => $message->payload]);
                    break;
                // Don't trigger error on timeout or EOF
                // TODO : add an option to allow user to choose to trigger an error or not
                case RD_KAFKA_RESP_ERR__TIMED_OUT:
                case RD_KAFKA_RESP_ERR__PARTITION_EOF:
                        break;
                default:
                    $this->getStream()->emit("error", [new \Exception($message->errstr(), $message->err)]);
                    break;
            }
        });

        return $this->getStream();
    }
}