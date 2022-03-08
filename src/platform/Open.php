<?php

namespace esp\weiXin\platform;


/**
 * Class Open
 * @package esp\weiXin\platform
 */
class Open
{
    private $platform;

    public function __construct(Platform $platform)
    {
        $this->platform = $platform;
    }

    public function check()
    {
        $api = '/cgi-bin/open/get?access_token={authorizer_access_token}';
        $value = $this->platform->Request($api, ['appid' => $this->platform->AppID], ['allow' => [89002]]);
        if (is_string($value)) return $value;
        if ($value['errcode'] === 89002) return false;
        return ['appid' => $value['open_appid']];
    }

    public function create()
    {
        $api = '/cgi-bin/open/create?access_token={authorizer_access_token}';
        $value = $this->platform->Request($api, ['appid' => $this->platform->AppID]);
        if (is_string($value)) return $value;
        return ['appid' => $value['open_appid']];
    }

    public function bind(string $openAppID)
    {
        $api = '/cgi-bin/open/bind?access_token={authorizer_access_token}';
        $value = $this->platform->Request($api, ['appid' => $this->platform->AppID, 'open_appid' => $openAppID]);
        if (is_string($value)) return $value;
        return $value['errcode'] === 0;
    }

    public function unbind(string $openAppID)
    {
        $api = '/cgi-bin/open/unbind?access_token={authorizer_access_token}';
        $value = $this->platform->Request($api, ['appid' => $this->platform->AppID, 'open_appid' => $openAppID]);
        if (is_string($value)) return $value;
        return $value['errcode'] === 0;
    }
}