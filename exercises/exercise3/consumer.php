<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/class_loader.php';

$loop = EventLoop\getLoop();

$scheduler = new \Rx\Scheduler\EventLoopScheduler($loop);

$httpd = new \Rxnet\Httpd\Httpd();

$redisConn = RedisConnector::connect()
    ->doOnError(function (\Exception $e) {
        printf("[%s]Failed to connect to Redis : %s\n", date('H:i:s'), $e->getMessage());
    })
    ->doOnNext(function () {
        echo "Redis is connected\n";
    });

$rabbitConn = RabbitConnector::connect()
    ->doOnError(function (\Exception $e) {
        printf("[%s]Failed to connect to Rabbit : %s\n", date('H:i:s'), $e->getMessage());
    })
    ->doOnNext(function () {
        echo "RabbitMq is connected\n";
    });


$redisConn->zip([$rabbitConn])
    ->subscribeCallback(
        function ($connData) use ($httpd, $loop, $scheduler) {
            RabbitConnector::getQueue()->consume()
                ->doOnNext(function (\Rxnet\RabbitMq\RabbitMessage $message) {
                    $data = $message->getData();
                    printf("[%s]Consumed rabbit :\n", date('H:i:s'));
                    var_dump($data);
                })
                ->subscribeCallback(
                    function (\Rxnet\RabbitMq\RabbitMessage $message) use ($connData, $scheduler) {
                        \Rx\Observable::just($message->getData())
                            ->flatMap(new ScrapRoute($connData[0]))
                            ->subscribeCallback(
                                function ($data) use ($message) {
                                    printf("[%s]Scrapped data :\n", date('H:i:s'));
                                    var_dump($data);
                                    $message->ack();
                                },
                                null,
                                null,
                                $scheduler
                            );
                    },
                    null,
                    null,
                    $scheduler
                );
        },
        null,
        null,
        $scheduler
);

$loop->run();