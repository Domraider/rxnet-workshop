<?php
require_once __DIR__ . '/../bootstrap.php';

$loop = EventLoop\getLoop();

$scheduler = new \Rx\Scheduler\EventLoopScheduler($loop);

$http = new \Rxnet\Http\Http();
$httpd = new \Rxnet\Httpd\Httpd();

$redis = new \Rxnet\Redis\Redis();
$redis->connect('redis://127.0.0.1:6379')
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

            $query1 = $http->get("http://127.0.0.1:24080/foo/{$queryItem}")
                ->timeout(5000)
                ->map($checkError)
                ->retry(3)
                ->map(function (\GuzzleHttp\Psr7\Response $response) {
                    // specific map
                    $result = json_decode($response->getBody(), true);
                    printf("[%s]Got response for foo, took %s seconds\n", date('H:i:s'), substr($result['time'], 0, -1));
                    return [
                        'type' => 'foo',
                        'result' => [
                            'word' => $result['item'],
                            'value' => $result['result']['value'],
                            'data' => $result['result']['set'],
                        ],
                    ];
                });

            $query2 = $http->get("http://127.0.0.1:24080/bar/{$queryItem}")
                ->timeout(5000)
                ->map($checkError)
                ->retry(3)
                ->map(function (\GuzzleHttp\Psr7\Response $response) {
                    // specific map
                    $result = json_decode($response->getBody(), true);
                    printf("[%s]Got response for bar, took %s\n", date('H:i:s'), $result['took']);
                    return [
                        'type' => 'bar',
                        'result' => [
                            'word' => $result['query'],
                            'value' => $result['result_value'],
                            'data' => $result['result_set'],
                        ],
                    ];
                });

            $query3 = $http->get("http://127.0.0.1:24080/foobar/{$queryItem}")
                ->timeout(5000)
                ->map($checkError)
                ->retry(3)
                ->map(function (\GuzzleHttp\Psr7\Response $response) {
                    // specific map
                    $result = explode("\n", (string)$response->getBody());
                    $meta = explode(":", $result[0]);
                    $data = explode("%%", $result[1]);
                    printf("[%s]Got response for foobar, took %s seconds\n", date('H:i:s'), $meta[2]);
                    return [
                        'type' => 'foobar',
                        'result' => [
                            'word' => $meta[1],
                            'value' => $meta[3],
                            'data' => $data,
                        ],
                    ];
                });

            $query4 = $http->get("http://127.0.0.1:24080/barfoo/{$queryItem}")
                ->timeout(5000)
                ->map($checkError)
                ->retry(3)
                ->map(function (\GuzzleHttp\Psr7\Response $response) {
                    // specific map
                    $result = json_decode($response->getBody(), true);
                    $key = key($result);
                    $result = current($result);
                    printf("[%s]Got response for barfoo, took %s seconds\n", date('H:i:s'), $result['time']);
                    return [
                        'type' => 'barfoo',
                        'result' => [
                            'word' => $key,
                            'value' => $result['data']['value'],
                            'data' => $result['data']['set'],
                        ],
                    ];
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
                    function ($setStatus) {
                        printf("Saved in redis: %s", $setStatus);
                    }, null, null,
                    $scheduler
                );

        });

        $httpd->listen(21000);
        printf("[%s]Server Listening on 21000\nUse : curl 127.0.0.1:21000/scrap/word_to_scrap\n", date('H:i:s'));
    },
        function (\Exception $e) {
            echo $e->getMessage()."\n";
        }
);

$loop->run();