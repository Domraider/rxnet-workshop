<?php
class BarApiMapper
{
    public function __invoke(\GuzzleHttp\Psr7\Response $response)
    {
        $result = json_decode($response->getBody(), true);
        printf("[%s]Got response for bar, took %s\n", date('H:i:s'), $result['took']);
        return [
            'type' => 'bar',
            'result' => [
                'word' => $result['query'],
                'value' => $result['result_value'],
                'data' => $result['result_set'],
            ],
        ];
    }
}