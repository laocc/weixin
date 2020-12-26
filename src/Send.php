<?php

namespace esp\weixin;

interface Send
{
    public function setFans(string $openID, string $nick);

    public function send(array $content, array $option = []);
}