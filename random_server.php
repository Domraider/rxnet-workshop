<?php
require_once __DIR__ . '/bootstrap.php';

define('RESPONSE_TIME_MAX_S', 20);
define('RESPONSE_TIME_OFFSET', 15);

define('RESPONSE_TIME_FACTOR', RESPONSE_TIME_MAX_S/exp(RESPONSE_TIME_OFFSET));

$loop = EventLoop\getLoop();
//$scheduler = new \Rx\Scheduler\EventLoopScheduler($loop);

$httpd = new \Rxnet\Httpd\Httpd();

$httpd->route('GET', '/', function(\Rxnet\Httpd\HttpdRequest $request, \Rxnet\Httpd\HttpdResponse $response) use ($loop) {
    $id = microtime(true) * 10000;
    printf("[%s][%d]Got Request\n", date("H:i:s"), $id);

    // random response delay
    $responseDelay = round((exp(mt_rand(1, 15)) * RESPONSE_TIME_FACTOR));
    printf("[%s][%d]Response in %d s\n", date("H:i:s"), $id, $responseDelay);

    $loop->addTimer(
        $responseDelay,
        function () use ($id, $response) {

            // sometime fails
            $failurePick = mt_rand(1, 100);
            switch ($failurePick) {
                case 10:
                    printf("[%s][%d]Failed : 404 not found\n", date("H:i:s"), $id);
                    $response->sendError('Not found', 404);
                    return;
                case 20:
                    printf("[%s][%d]Failed : 500 internal server error\n", date("H:i:s"), $id);
                    $response->sendError('Internal server error', 500);
                    return;
                case 30:
                    printf("[%s][%d]Failed : 502 bad gateway\n", date("H:i:s"), $id);
                    $response->sendError('Bad gateway', 502);
                    return;
            }

            printf("[%s][%d]Send Response\n", date("H:i:s"), $id);
            $response->json([
                [
                    'id' => 1,
                    'value' => 'foo1',
                ],
                [
                    'id' => 2,
                    'value' => 'foo2',
                ],
                [
                    'id' => 3,
                    'value' => 'foo3',
                ],
            ]);
        }
    );
});

$httpd->listen(24080);
$loop->run();