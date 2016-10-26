<?php
class ProducerRoute
{
    /** @var \React\EventLoop\LoopInterface  */
    protected $loop;
    /** @var \Rxnet\Http\Http  */
    protected $http;
    /** @var \Rxnet\Redis\Redis  */
    protected $redis;
    /** @var \Rxnet\RabbitMq\RabbitExchange  */
    protected $rabbitExchange;

    public function __construct(\React\EventLoop\LoopInterface $loop, \Rxnet\Redis\Redis $redis, \Rxnet\RabbitMq\RabbitExchange $rabbitExchange)
    {
        $this->loop = $loop;
        $this->redis = $redis;
        $this->rabbitExchange = $rabbitExchange;

        $this->http = new \Rxnet\Http\Http();
    }

    public function __invoke(\Rxnet\Httpd\HttpdRequest $request, \Rxnet\Httpd\HttpdResponse $response)
    {
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
                return $this->rabbitExchange->produce($queryItem, '/my/items');
            })
            ->subscribeCallback(function () use ($queryItem, $response) {
                $response->text(sprintf("Produced in rabbitmq : %s\n", $queryItem));
            });

    }
}