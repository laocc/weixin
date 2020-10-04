<?php

namespace laocc\weixin\items;


abstract class Send extends Base
{
    abstract public function setFans(string $openID, string $nick);
    abstract public function send(array $content, array $option = []);
}