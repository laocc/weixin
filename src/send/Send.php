<?php

namespace esp\weiXin\send;

interface Send
{
    public function send(array $content, array $option = []);
}