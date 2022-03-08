<?php

namespace esp\weiXin;

class Hash
{
    private $redis;
    private $key;

    public function __construct(\Redis $redis, string $hashKey)
    {
        $this->redis = $redis;
        $this->key = $hashKey;
    }

    public function get(string $key)
    {
        return $this->redis->hGet($this->key, $key);
    }

    public function set(string $key, $value)
    {
        return $this->redis->hSet($this->key, $key, $value);
    }

}