<?php

namespace esp\weiXin\express;

class Express extends _Base
{
    public function company()
    {
        $api = "/cgi-bin/express/delivery/open_msg/get_delivery_list?access_token={access_token}";
        $val = $this->Request($api, '{}', ['type' => 'post']);

        return array_combine(
            array_column($val['delivery_list'], 'delivery_id'),
            array_column($val['delivery_list'], 'delivery_name')
        );
    }
}