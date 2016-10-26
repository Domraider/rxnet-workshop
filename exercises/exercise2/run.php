<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/class_loader.php';

$loop = EventLoop\getLoop();

$scheduler = new \Rx\Scheduler\EventLoopScheduler($loop);

$httpd = new \Rxnet\Httpd\Httpd();

RedisConnector::connect()
    ->doOnError(function (\Exception $e) {
        printf("[%s]Failed to connect to Redis : %s\n", date('H:i:s'), $e->getMessage());
    })
    ->doOnNext(function () {
        echo "Redis is connected\n";
    })->subscribeCallback(
        function ($redis) use ($httpd, $loop, $scheduler) {
            $httpd->route(
                'GET', '/scrap/{item}',
                function (\Rxnet\Httpd\HttpdRequest $request, \Rxnet\Httpd\HttpdResponse $response) use ($scheduler, $redis) {
                    $route = new ScrapRoute($redis);
                    return $route->__invoke($request->getRouteParam('item'))
                        ->doOnNext(function ($data) use ($response) {
                            $response->json($data);
                        })
                        ->subscribeCallback(
                            null, null, null,
                            $scheduler
                        );
                }
            );

            $httpd->listen(21002);
            printf("[%s]Server Listening on 21002\nUse : curl 127.0.0.1:21002/scrap/word_to_scrap\n", date('H:i:s'));
        }
    );

$loop->run();
