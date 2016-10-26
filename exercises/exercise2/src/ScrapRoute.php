<?php
class ScrapRoute
{
    /** @var \Rxnet\Http\Http  */
    protected $http;
    /** @var \Rxnet\Redis\Redis  */
    protected $redis;

    public function __construct(\Rxnet\Redis\Redis $redis)
    {
        $this->redis = $redis;

        $this->http = new \Rxnet\Http\Http();
    }

    public function __invoke($queryItem)
    {
        $checkError = function (\GuzzleHttp\Psr7\Response $response) {
            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \Exception(sprintf("Http request failed with code %s : %s", $statusCode, $response->getReasonPhrase()), $statusCode);
            }

            return $response;
        };

        $query1 = $this->http->get("http://127.0.0.1:24080/foo/{$queryItem}")
            ->timeout(5000)
            ->map($checkError)
            ->retry(3)
            ->map(new FooApiMapper());

        $query2 = $this->http->get("http://127.0.0.1:24080/bar/{$queryItem}")
            ->timeout(5000)
            ->map($checkError)
            ->retry(3)
            ->map(new BarApiMapper());

        $query3 = $this->http->get("http://127.0.0.1:24080/foobar/{$queryItem}")
            ->timeout(5000)
            ->map($checkError)
            ->retry(3)
            ->map(new FoobarApiMapper());

        $query4 = $this->http->get("http://127.0.0.1:24080/barfoo/{$queryItem}")
            ->timeout(5000)
            ->map($checkError)
            ->retry(3)
            ->map(new BarfooApiMapper());

        $result = $query1->zip([$query2, $query3, $query4]);

        return $result
            ->map(function ($data) {
                // global map
                foreach ($data as $i => $item) {
                    $data[$item['type']] = $item['result'];
                    unset($data[$i]);
                }
                return $data;
            })
            ->doOnNext(function ($data) use ($queryItem) {
                //save in redis
                return $this->redis->set(sprintf('my_set:%s', $queryItem), json_encode($data));
            });

    }
}