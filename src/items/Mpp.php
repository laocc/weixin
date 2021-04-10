<?php

namespace esp\weiXin\items;

use esp\weiXin\Base;

final class Mpp extends Base
{

    /**
     * 生成二维码
     * 临时码的scene_id为32位非0整形，也就是小于：4,294,967,295
     *
     * @param string $scene
     * @param int $expire
     * @return array|mixed|string
     * https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1443433542
     */
    /**
     * @param int $id
     * @param string $str
     * @param int $expire
     * @return mixed
     * @throws \Exception
     */
    public function load_QrTick(int $id, string $str, int $expire = 0)
    {
        //若$expire小于30，则该值表示的为天，转换为秒
        if ($expire > 0 and $expire <= 30) $expire = $expire * 86400;

        //$expire不可超过30天有效期
        if ($expire > 2592000) $expire = 2592000;

        /**
         * 二维码类型，
         * QR_SCENE:            为临时的整型参数值，
         * QR_STR_SCENE:        为临时的字符串参数值，
         * QR_LIMIT_SCENE:      为永久的整型参数值，
         * QR_LIMIT_STR_SCENE:  为永久的字符串参数值
         */
        $data = [];
        if ($expire > 0) {//临时码
            $data['expire_seconds'] = $expire;
            if (empty($str)) {
                $data['action_name'] = 'QR_SCENE';
                $data['action_info'] = ['scene' => ['scene_id' => $id]];
            } else {
                $data['action_name'] = 'QR_STR_SCENE';
                $data['action_info'] = ['scene' => ['scene_str' => $str]];
            }

        } else { //永久码
            $data['action_name'] = 'QR_LIMIT_STR_SCENE';
            $data['action_info'] = ['scene' => ['scene_id' => $id, 'scene_str' => $str]];
        }

        $api = "/cgi-bin/qrcode/create?access_token={access_token}";
        $JsonStr = $this->Request($api, $data);
        return $JsonStr;
    }


}