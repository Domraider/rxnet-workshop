<?php
class FooApiMapper
{
    public function __invoke(\GuzzleHttp\Psr7\Response $response)
    {
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
    }
}