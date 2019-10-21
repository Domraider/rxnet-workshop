<?php
require_once __DIR__ . '/../bootstrap.php';

define('FORMAT_1', 'f0o');
define('FORMAT_2', 'b4r');
define('FORMAT_3', 'f0ob4r');

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

$httpd->route('GET', '/{format}/{item}', function(\Rxnet\Httpd\HttpdRequest $request, \Rxnet\Httpd\HttpdResponse $response) use ($loop, $setTemplates) {
    $format = $request->getRouteParam('format');
    $queryItem = $request->getRouteParam('item');

    mt_srand(time(), MT_RAND_MT19937);

    $id = microtime(true) * 10000 .  '-'  . $queryItem;
    printf("[%s][%d]Got Request /%s/%s\n", date('H:i:s'), $id, $format, $queryItem);

    // random response delay
    $responseDelay = round((exp(mt_rand(1, 15)) * RESPONSE_TIME_FACTOR));
    printf("[%s][%d]Response in %d s\n", date('H:i:s'), $id, $responseDelay, $setTemplates);

    $loop->addTimer(
        $responseDelay,
        function () use ($id, $queryItem, $format, $responseDelay, $response, $setTemplates) {

            // sometime fails
            $failurePick = mt_rand(1, 100);
            switch ($failurePick) {
                case 11:
                case 12:
                case 13:
                case 14:
                case 15:
                    printf("[%s][%d]Failed : 404 not found\n", date('H:i:s'), $id);
                    $response->sendError('Not found', 404);
                    return;
                case 21:
                case 22:
                case 23:
                case 24:
                case 25:
                    printf("[%s][%d]Failed : 500 internal server error\n", date('H:i:s'), $id);
                    $response->sendError('Internal server error', 500);
                    return;
                case 31:
                case 32:
                case 33:
                    printf("[%s][%d]Failed : 502 bad gateway\n", date('H:i:s'), $id);
                    $response->sendError('Bad gateway', 502);
                    return;
            }

            printf("[%s][%d]Send Response\n", date('H:i:s'), $id);
            mt_srand(crc32($queryItem.$format)); // for mt_rand
            srand(crc32($queryItem.$format)); // for shuffle

            $set = [];
            shuffle($setTemplates);
            for ($i=0; $i<mt_rand(5, 10); $i++) {
                $set[] = sprintf($setTemplates[$i], $queryItem);
            }

            switch ($format) {
                case FORMAT_1:
                    $response->json([
                        'id' => $id,
                        'item' => $queryItem,
                        'time' => $responseDelay.'s',
                        'result' => [
                            'set' => $set,
                            'value' => strlen($queryItem) * mt_rand(100,1000),
                        ]
                    ]);
                    return;
                case FORMAT_2:
                    $response->json([
                        '_id' => $id,
                        'query' => $queryItem,
                        'took' => $responseDelay.' seconds',
                        'result_set' => $set,
                        'result_value' => strlen($queryItem) * mt_rand(100,1000),
                    ]);
                    return;
                case FORMAT_3:
                    $response->text(sprintf("%s:%s:%s:%s\n%s\n", $id, $queryItem, $responseDelay, strlen($queryItem) * mt_rand(100,1000), implode("%%", $set)));
                    return;
                default:
                    $response->json([
                        $id => [
                            'item' => $queryItem,
                            'time' => $responseDelay,
                            'data' => [
                                'set' => $set,
                                'value' => strlen($queryItem) * mt_rand(100,1000),
                            ],
                        ],
                    ]);
                    return;
            }
        }
    );
});

$httpd->listen(24080);
printf("[%s]Server Listening on 24080\n", date('H:i:s'));
$loop->run();
