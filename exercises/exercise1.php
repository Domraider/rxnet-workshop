<?php
require_once __DIR__ . '/bootstrap.php';

$loop = EventLoop\getLoop();

$scheduler = new \Rx\Scheduler\EventLoopScheduler($loop);

$http = new \Rxnet\Http\Http();
$httpd = new \Rxnet\Httpd\Httpd();

$redis = new \Rxnet\Redis\Redis();
$redis->connect('localhost:6379')
    ->doOnNext(function () {
        echo "Redis is connected\n";
    })->subscribeCallback(function () use ($httpd, $loop, $scheduler, $http, $redis) {
        $httpd->route('GET', '/scrap/{item}', function(\Rxnet\Httpd\HttpdRequest $request, \Rxnet\Httpd\HttpdResponse $response) use ($loop, $scheduler, $http, $redis) {
            $queryItem = $request->getRouteParam('item');
            $checkError = function (\GuzzleHttp\Psr7\Response $response) {
                $statusCode = $response->getStatusCode();
                if ($statusCode < 200 || $statusCode >= 300) {
                    throw new \Exception(sprintf("Http request failed with code %s : %s", $statusCode, $response->getReasonPhrase()), $statusCode);
                }

                return $response;
            };

            $query1 = $http->get("http://127.0.0.1:23080/foo?item={$queryItem}")
                ->timeout(30000)
                ->map($checkError)
                ->retry(3)
                ->map(function (\GuzzleHttp\Psr7\Response $response) {
                    return [
                        'type' => 'foo',
                        'result' => json_decode($response->getBody(), true),
                    ];
                    // specific map
                });

            $query2 = $http->get("http://127.0.0.1:23080/bar?item={$queryItem}")
                ->timeout(30000)
                ->map($checkError)
                ->retry(3)
                ->map(function (\GuzzleHttp\Psr7\Response $response) {
                    return [
                        'type' => 'bar',
                        'result' => json_decode($response->getBody(), true),
                    ];
                    // specific map
                });

            $query3 = $http->get("http://127.0.0.1:23080/foobar?item={$queryItem}")
                ->timeout(30000)
                ->map($checkError)
                ->retry(3)
                ->map(function (\GuzzleHttp\Psr7\Response $response) {
                    return [
                        'type' => 'foobar',
                        'result' => json_decode($response->getBody(), true),
                    ];
                    // specific map
                });

            $query4 = $http->get("http://127.0.0.1:23080/barfoo?item={$queryItem}")
                ->timeout(30000)
                ->map($checkError)
                ->retry(3)
                ->map(function (\GuzzleHttp\Psr7\Response $response) {
                    return [
                        'type' => 'barfoo',
                        'result' => json_decode($response->getBody(), true),
                    ];
                    // specific map
                });

            $result = $query1->zip([$query2, $query3, $query4]);

            $result
                ->map(function ($data) {
                    // global map
                    foreach ($data as $i => $item) {
                        $data[$item['type']] = $item['result'];
                        unset($data[$i]);
                    }
                    return $data;
                })
                ->doOnNext(function ($data) use ($response) {
                    $response->json($data);
                })
                ->flatMap(function ($data) use ($redis, $queryItem) {
                    //save in redis
                    return $redis->set(sprintf('my_set:%s', $queryItem), json_encode($data));
                })
                ->subscribeCallback(
                    null, null, null,
                    $scheduler
                );

        });

        $httpd->listen(21000);
    }
);

$loop->run();