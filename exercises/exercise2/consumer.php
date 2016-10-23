<?php
use Rxnet\RabbitMq\RabbitMessage;

require_once __DIR__ . '/../bootstrap.php';

class Exercise2Consumer
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
                    ->doOnNext(function () {
                        $this->queue = $this->rabbit->queue('test_queue', 'amq.direct', []);
                        echo "Rabbit is connected\n";
                    })
            ])
            ->subscribeCallback(function () {
                // run httpd server
                $this->queue->consume()
                    ->subscribeCallback(function (RabbitMessage $message) {
                        $data = $message->getData();
                        // todo call http exercise 1 ?

                        // Do what you want but do one of this to get next
                        //$message->ack();
                    });
            });

        $this->loop->run();
    }
}

$main = new Exercise2Consumer();
$main->run();