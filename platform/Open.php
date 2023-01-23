<?php

namespace esp\weiXin\platform;


/**
 * 创建开放平台帐号并绑定公众号/小程序
 * 该 API 用于创建一个开放平台帐号，并将一个尚未绑定开放平台帐号的公众号/小程序绑定至该开放平台帐号上。
 * 新创建的开放平台帐号的主体信息将设置为与之绑定的公众号或小程序的主体。
 *
 * Class Open
 * @package esp\weiXin\platform
 */
class Open extends _Base
{

    /**
     * @return array|string
     * https://developers.weixin.qq.com/doc/oplatform/Third-party_Platforms/2.0/api/account/create.html
     */
    public function create()
    {
        $api = '/cgi-bin/open/create?access_token={authorizer_access_token}';
        $value = $this->Request($api, ['appid' => $this->AppID]);
        if (is_string($value)) return $value;
        return ['appid' => $value['open_appid']];
    }

    /**
     * @return array|false|string
     * https://developers.weixin.qq.com/doc/oplatform/Third-party_Platforms/2.0/api/account/get.html
     */
    public function check()
    {
        $api = '/cgi-bin/open/get?access_token={authorizer_access_token}';
        $value = $this->Request($api, ['appid' => $this->AppID], ['allow' => [89002]]);
        if (is_string($value)) return $value;
        if ($value['errcode'] === 89002) return false;
        return ['appid' => $value['open_appid']];
    }

    /**
     * @param string $openAppID
     * @return bool|string
     * https://developers.weixin.qq.com/doc/oplatform/Third-party_Platforms/2.0/api/account/bind.html
     */
    public function bind(string $openAppID)
    {
        $api = '/cgi-bin/open/bind?access_token={authorizer_access_token}';
        $value = $this->Request($api, ['appid' => $this->AppID, 'open_appid' => $openAppID]);
        if (is_string($value)) return $value;
        return $value['errcode'] === 0;
    }

    /**
     * @param string $openAppID
     * @return bool|string
     * https://developers.weixin.qq.com/doc/oplatform/Third-party_Platforms/2.0/api/account/unbind.html
     */
    public function unbind(string $openAppID)
    {
        $api = '/cgi-bin/open/unbind?access_token={authorizer_access_token}';
        $value = $this->Request($api, ['appid' => $this->AppID, 'open_appid' => $openAppID]);
        if (is_string($value)) return $value;
        return $value['errcode'] === 0;
    }
}