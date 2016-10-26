<?php
class RabbitConnector
{
    /** @var \Rxnet\RabbitMq\RabbitMq  */
    protected static $mq;
    /** @var  \Rxnet\RabbitMq\RabbitQueue */
    protected static $queue;
    /** @var  \Rxnet\RabbitMq\RabbitExchange */
    protected static $exchange;

    public function __invoke()
    {
        return self::connect();
    }

    public static function connect()
    {
        self::$mq = new \Rxnet\RabbitMq\RabbitMq(
            'rabbit://guest:guest@127.0.0.1:5672/',
            new \Rxnet\Serializer\Serialize()
        );

        return self::$mq->connect()
            ->flatMap(function () {
                self::$queue = self::$mq->queue('test_queue', 'amq.direct', []);
                return self::$queue->create(\Rxnet\RabbitMq\RabbitQueue::DURABLE);
            })
            ->flatMap(function () {
                self::$exchange = self::$mq->exchange('amq.direct');
                return self::$exchange->create(\Rxnet\RabbitMq\RabbitExchange::TYPE_DIRECT, [
                        \Rxnet\RabbitMq\RabbitExchange::DURABLE,
                        \Rxnet\RabbitMq\RabbitExchange::AUTO_DELETE
                    ])
                    ->flatMap(function () {
                        return self::$queue->bind('/my/items', 'amq.direct');
                    });
            });
    }

    /**
     * @return \Rxnet\RabbitMq\RabbitMq
     */
    public static function getMq()
    {
        return self::$mq;
    }

    /**
     * @return \Rxnet\RabbitMq\RabbitQueue
     */
    public static function getQueue()
    {
        return self::$queue;
    }

    /**
     * @return \Rxnet\RabbitMq\RabbitExchange
     */
    public static function getExchange()
    {
        return self::$exchange;
    }
}