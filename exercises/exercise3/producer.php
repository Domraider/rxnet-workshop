<?php
require_once __DIR__ . '/../bootstrap.php';

class Exercise3Producer
{
    protected $loop;
    protected $scheduler;
    protected $http;
    protected $httpd;
    /** @var \Rxnet\Redis\Redis  */
    protected $redis;
    /** @var \Rxnet\RabbitMq\RabbitMq  */
    protected $rabbit;
    /** @var  \Rxnet\RabbitMq\RabbitQueue */
    protected $queue;
    /** @var  \Rxnet\RabbitMq\RabbitExchange */
    protected $exchange;

    public function __construct()
    {
        $this->loop = EventLoop\getLoop();

        $this->scheduler = new \Rx\Scheduler\EventLoopScheduler($this->loop);

        $this->http = new \Rxnet\Http\Http();
        $this->httpd = new \Rxnet\Httpd\Httpd();

        $this->redis = new \Rxnet\Redis\Redis();

        $this->rabbit = new \Rxnet\RabbitMq\RabbitMq('rabbit://guest:guest@127.0.0.1:5672/', new \Rxnet\Serializer\Serialize());
    }

    public function run()
    {
        // connect rethink
        $this->redis->connect('localhost:6379')
            ->doOnNext(function () {
                echo "Redis is connected\n";
            })
            ->zip([
                // connect rabbit and create queue + exchange if not exist
                $this->rabbit->connect()
                    ->flatMap(function () {
                        $this->queue = $this->rabbit->queue('test_queue', 'amq.direct', []);
                        return $this->queue->create(\Rxnet\RabbitMq\RabbitQueue::DURABLE);
                    })
                    ->flatMap(function () {
                        $this->exchange = $this->rabbit->exchange('amq.direct');
                        return $this->exchange->create(\Rxnet\RabbitMq\RabbitExchange::TYPE_DIRECT, [
                            \Rxnet\RabbitMq\RabbitExchange::DURABLE,
                            \Rxnet\RabbitMq\RabbitExchange::AUTO_DELETE
                        ]);
                    })
                    ->flatMap(function () {
                        return $this->queue->bind('/my/items', 'amq.direct');
                    })
                    ->doOnNext(function () {
                        echo "Rabbit is connected\n";
                    })
            ])
            ->subscribeCallback(function () {
                // run httpd server
                $this->httpd->route('GET', '/scrap/{item}', function(\Rxnet\Httpd\HttpdRequest $request, \Rxnet\Httpd\HttpdResponse $response) {
                    $queryItem = $request->getRouteParam('item');

                    return $this->redis->exists(sprintf('my_set:%s', $queryItem))
                        ->filter(function ($exists) use ($queryItem, $response) {
                            if ((bool)$exists) {
                                $response->text(sprintf("Item %s already exists\n", $queryItem));
                                return false;
                            }

                            return true;
                        })
                        ->flatMap(function () use ($queryItem) {
                            return $this->exchange->produce($queryItem, '/my/items');
                        })
                        ->subscribeCallback(function () use ($queryItem, $response) {
                            $response->text(sprintf("Produced in rabbitmq : %s\n", $queryItem));
                        });
                });

                $this->httpd->listen(21002);
            }
            );

        $this->loop->run();
    }
}

$main = new Exercise3Producer();
$main->run();