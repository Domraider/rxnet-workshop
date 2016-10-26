<?php
class BarfooApiMapper
{
    public function __invoke(\GuzzleHttp\Psr7\Response $response)
    {
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
    }
}