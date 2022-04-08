<?php

namespace esp\weiXin\app;

use esp\weiXin\Base;
use Exception;

class Scheme extends Base
{
    /**
     * @param array $jump
     * @return array|string
     * @throws Exception
     *
     * https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/url-scheme/urlscheme.generate.html
     */
    public function create(array $jump)
    {
        $value = [];
        $value['is_expire'] = isset($jump['expire']);

        if (isset($jump['expire'])) {
            if ($jump['expire'] > 10000) {//这是时间戳
                $value['expire_type'] = 0;
                $value['expire_time'] = $jump['expire'];
            } else {//间隔的天数
                $value['expire_type'] = 1;
                $value['expire_interval'] = $jump['expire'];
            }
        }

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