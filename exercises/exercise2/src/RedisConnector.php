<?php
class RedisConnector
{
    public function __invoke()
    {
        return self::connect();
    }

    public static function connect()
    {
        $redis = new \Rxnet\Redis\Redis();
        return $redis->connect('localhost:6379');
    }
}