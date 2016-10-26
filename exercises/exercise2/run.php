<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/class_loader.php';

$loop = EventLoop\getLoop();

$scheduler = new \Rx\Scheduler\EventLoopScheduler($loop);

$httpd = new \Rxnet\Httpd\Httpd();

$redis = new \Rxnet\Redis\Redis();
$redis->connect('localhost:6379')
    ->doOnNext(function () {
        echo "Redis is connected\n";
    })->subscribeCallback(function () use ($httpd, $loop, $scheduler, $redis) {
        $httpd->route('GET', '/scrap/{item}', new ScrapRoute($loop, $redis));

        $httpd->listen(21002);
        printf("[%s]Server Listening on 21002\nUse : curl 127.0.0.1:21000/scrap/word_to_scrap\n", date('H:i:s'));
    }
    );

$loop->run();