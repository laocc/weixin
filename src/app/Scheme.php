<?php

namespace esp\weiXin\app;

use esp\weiXin\Base;

class Scheme extends Base
{
    public function create(array $jump)
    {
        $value = [];
        $value['is_expire'] = false;
        $value['jump_wxa'] = [];
        $value['jump_wxa']['path'] = $jump['path'] ?? '';
        $value['jump_wxa']['query'] = $jump['query'] ?? '';
        if (is_array($value['jump_wxa']['query'])) {
            $value['jump_wxa']['query'] = http_build_query($value['jump_wxa']['query']);
        }

        $api = "/wxa/generatescheme?access_token={access_token}";
        $get = $this->Request($api, $value);
        if (is_string($get)) return $get;
        if (strtolower($get['errmsg'] ?? '') === 'ok') return $get;//['openlink'];
        return $get['errmsg'];
    }
}