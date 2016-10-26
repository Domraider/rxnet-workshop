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
            $httpd->route('GET', '/produce/{item}', new ProducerRoute($loop, $connData[0], RabbitConnector::getExchange()));

            $httpd->listen(21003);
            printf("[%s]Server Listening on 21003\nUse : curl 127.0.0.1:21003/produce/word_to_scrap\n", date('H:i:s'));
        },
        null,
        null,
        $scheduler
);

$loop->run();