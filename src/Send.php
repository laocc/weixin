<?php

namespace esp\weiXin;

interface Send
{
    public function setFans(string $openID, string $nick);

    public function send(array $content, array $option = []);
}