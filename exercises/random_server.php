<?php
require_once __DIR__ . '/bootstrap.php';

define('RESPONSE_TIME_MAX_S', 20);
define('RESPONSE_TIME_OFFSET', 15);

define('RESPONSE_TIME_FACTOR', RESPONSE_TIME_MAX_S/exp(RESPONSE_TIME_OFFSET));

$loop = EventLoop\getLoop();
//$scheduler = new \Rx\Scheduler\EventLoopScheduler($loop);

$httpd = new \Rxnet\Httpd\Httpd();

$setTemplates = [
    '%s',
    'I like %s',
    '%s is amazing !',
    'What is %s ?',
    'Is %s dead or alive ?',
    'I eat %s at breakfast',
    '%s is in the kitchen',
    'I hate %s',
    'I use to use %s',
    '%s is like %1$s',
];

$httpd->route('GET', '/{item}', function(\Rxnet\Httpd\HttpdRequest $request, \Rxnet\Httpd\HttpdResponse $response) use ($loop, $setTemplates) {
    $queryItem = $request->getRouteParam('item');

    $id = microtime(true) * 10000 .  '-'  . $queryItem;
    printf("[%s][%d]Got Request\n", date('H:i:s'), $id);

    // random response delay
    $responseDelay = round((exp(mt_rand(1, 15)) * RESPONSE_TIME_FACTOR));
    printf("[%s][%d]Response in %d s\n", date('H:i:s'), $id, $responseDelay, $setTemplates);

    $loop->addTimer(
        $responseDelay,
        function () use ($id, $queryItem, $responseDelay, $response, $setTemplates) {

            // sometime fails
            $failurePick = mt_rand(1, 100);
            switch ($failurePick) {
                case 10:
                    printf("[%s][%d]Failed : 404 not found\n", date('H:i:s'), $id);
                    $response->sendError('Not found', 404);
                    return;
                case 20:
                    printf("[%s][%d]Failed : 500 internal server error\n", date('H:i:s'), $id);
                    $response->sendError('Internal server error', 500);
                    return;
                case 30:
                    printf("[%s][%d]Failed : 502 bad gateway\n", date('H:i:s'), $id);
                    $response->sendError('Bad gateway', 502);
                    return;
            }

            printf("[%s][%d]Send Response\n", date('H:i:s'), $id);
            mt_srand(crc32($queryItem)); // for mt_rand
            srand(crc32($queryItem)); // for shuffle

            $set = [];
            shuffle($setTemplates);
            for ($i=0; $i<mt_rand(5, 10); $i++) {
                $set[] = sprintf($setTemplates[$i], $queryItem);
            }

            $response->json([
                [
                    'id' => $id,
                    'item' => $queryItem,
                    'time' => $responseDelay.'s',
                    'result' => [
                        'set' => $set,
                        'value' => strlen($queryItem) * mt_rand(100,1000),
                    ]
                ]
            ]);
        }
    );
});

$httpd->listen(24080);
$loop->run();
