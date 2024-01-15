<?php

namespace esp\weiXin\express;

class Express extends _Base
{
    public function company()
    {
        $value = [];
        $api = "/cgi-bin/express/delivery/open_msg/get_delivery_list?access_token={access_token}";
        return $this->Request($api, $value);
    }
}