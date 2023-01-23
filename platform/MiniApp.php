<?php

namespace esp\weiXin\platform;

class MiniApp extends _Base
{

    /**
     * @param array $param
     * @return array|mixed|string
     * https://developers.weixin.qq.com/doc/oplatform/Third-party_Platforms/2.0/api/Register_Mini_Programs/Fast_Registration_Interface_document.html
     */
    public function create(array $param)
    {
        $option = [];
        $api = "/cgi-bin/component/fastregisterweapp?action=create&component_access_token={component_access_token}";
        return $this->Request($api, $param, $option);
    }

    public function query(array $param)
    {
        $option = [];
        $option['allow'] = ['all'];
        $api = "/cgi-bin/component/fastregisterweapp?action=search&component_access_token={component_access_token}";
        return $this->Request($api, $param, $option);
    }


}