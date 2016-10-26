<?php
class FoobarApiMapper
{
    public function __invoke(\GuzzleHttp\Psr7\Response $response)
    {
        $result = explode("\n", (string)$response->getBody());
        $meta = explode(":", $result[0]);
        $data = explode("%%", $result[1]);
        printf("[%s]Got response for foobar, took %s seconds\n", date('H:i:s'), $meta[2]);
        return [
            'type' => 'foobar',
            'result' => [
                'word' => $meta[1],
                'value' => $meta[3],
                'data' => $data,
            ],
        ];
    }
}