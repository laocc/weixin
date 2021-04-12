<?php

namespace esp\weiXin;

interface Send
{
    public function send(array $content, array $option = []);
}