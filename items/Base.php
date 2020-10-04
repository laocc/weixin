<?php

namespace laocc\weixin\items;


use laocc\weixin\WeiChat;

abstract class Base
{
    protected $wx;

    public function __construct(WeiChat $wx)
    {
        $this->wx = $wx;
    }

    protected function Request($OpenID)
    {
        $api = "/cgi-bin/user/info?access_token={access_token}&openid={$OpenID}&lang=zh_CN";
        $info = $this->wx->Request($api);
        if (is_string($info)) return $info;
        return $info;
    }

}