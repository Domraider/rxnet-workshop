<?php
require_once __DIR__ . '/bootstrap.php';

$loop = EventLoop\getLoop();

$scheduler = new \Rx\Scheduler\EventLoopScheduler($loop);

$http = new \Rxnet\Http\Http();

$query1 = $http->get("http://127.0.0.1:23080/foo")
    ->retry(3)
    ->doOnError(function (\Exception $e) {
        printf("[%s]Error : %s\n", date('H:i:s'), $e->getMessage());
    })
    ->map(function (\GuzzleHttp\Psr7\Response $response) {
        return json_decode($response->getBody(), true);
    });

$query2 = $http->get("http://127.0.0.1:23080/bar")
    ->retry(3)
    ->doOnError(function (\Exception $e) {
        printf("[%s]Error : %s\n", date('H:i:s'), $e->getMessage());
    })
    ->map(function (\GuzzleHttp\Psr7\Response $response) {
        return json_decode($response->getBody(), true);
    });

$query3 = $http->get("http://127.0.0.1:23080/foobar")
    ->retry(3)
    ->doOnError(function (\Exception $e) {
        printf("[%s]Error : %s\n", date('H:i:s'), $e->getMessage());
    })
    ->map(function (\GuzzleHttp\Psr7\Response $response) {
        return json_decode($response->getBody(), true);
    });

$query4 = $http->get("http://127.0.0.1:23080/barfoo")
    ->retry(3)
    ->doOnError(function (\Exception $e) {
        printf("[%s]Error : %s\n", date('H:i:s'), $e->getMessage());
    })
    ->map(function (\GuzzleHttp\Psr7\Response $response) {
        return json_decode($response->getBody(), true);
    });

$result = $query1->zip([$query2, $query3, $query4]);

$result
    ->map(function ($data) {
        return call_user_func_array('array_merge', $data);
    })
    ->subscribeCallback(
        function ($data) {
            foreach ($data as $item) {
                printf("id:%s, value:%s\n", $item['id'], $item['value']);
                // todo save in base
            }
        },
        function ($e) {
            $in = true;
        }
    );