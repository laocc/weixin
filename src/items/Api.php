<?php

namespace esp\weiXin\items;

use esp\weiXin\Base;
use Exception;
use function esp\helper\str_rand;

final class Api extends Base
{

    /**
     * 获取jsapi_ticket
     * @return array|int|string
     * @throws Exception
     */
    private function load_ApiTicket()
    {
        $ticket = $this->Hash()->get("ApiTicket_{$this->AppID}");
        if ($ticket and $ticket['expires'] > time()) return $ticket;

        $api = "/cgi-bin/ticket/getticket?type=jsapi&access_token={access_token}";
        $dat = $this->Request($api);
        if (is_string($dat)) return $dat;

        $dat = ['ticket' => $dat['ticket'], 'expires' => intval($dat['expires_in']) + time() - 100];

        $this->Hash()->set("ApiTicket_{$this->AppID}", $dat);
        return $dat;
    }


    /**
     *  生成微信JS-SDK的config
     *
     * 调用接口，请参考
     * https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/JS-SDK.html#63
     *
     * @param string $url
     * @param array $api
     * @param bool $debug
     * @return array|string
     * @throws Exception
     */
    public function ApiTicketJS(string $url, array $api = [], bool $debug = false)
    {
        if (empty($api)) {
            array_push($api, 'error', 'checkJsApi', 'updateAppMessageShareData', 'updateTimelineShareData');
        }

        $ticket = $this->load_ApiTicket();
        if (is_string($ticket)) return $ticket;

        $arrValue = array();
        $arrValue['noncestr'] = str_rand(30);
        $arrValue['timestamp'] = time();
        $arrValue['jsapi_ticket'] = $ticket['ticket'];
        $arrValue['url'] = $url;
        ksort($arrValue);
        $buff = [];
        foreach ($arrValue as $k => &$v) $buff[] = "{$k}={$v}";
        $rawString = implode('&', $buff);

        $arrApi = array();
        $arrApi['debug'] = boolval($debug);// 开启调试模式,
        $arrApi['appId'] = $this->AppID;// 必填，公众号的唯一标识
        $arrApi['timestamp'] = $arrValue['timestamp']; // 必填，生成签名的时间戳
        $arrApi['nonceStr'] = $arrValue['noncestr'];// 必填，生成签名的随机串
        $arrApi['signature'] = sha1($rawString);// 必填，签名
        $arrApi['jsApiList'] = $api;// 必填

        return $arrApi;
    }
}