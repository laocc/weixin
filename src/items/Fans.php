<?php

namespace esp\weiXin\items;


use esp\weiXin\Platform;
use esp\weiXin\Base;

final class Fans extends Base
{

    /**
     * 读取某一个用户
     * https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140839
     * @param $OpenID
     * @return array|mixed|string
     * @throws \Exception
     */
    public function load_FansInfo($OpenID)
    {
        $api = "/cgi-bin/user/info?access_token={access_token}&openid={$OpenID}&lang=zh_CN";
        $info = $this->Request($api);
        if (is_string($info)) return $info;
        return $info;
    }

    /**
     * 拉取全部粉丝
     * @param $call
     * @param $next
     * @return int
     * @throws \Exception
     * https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140840
     */
    public function load_AllFans(callable $call, string $next = '')
    {
        $apiBase = "/cgi-bin/user/get?access_token={access_token}&next_openid=%s";
        $sumFans = 0;
        while (true) {
            $api = sprintf($apiBase, $next);
            $info = $this->Request($api);
            if (is_string($info)) {
                \esp\helper\_echo("{$this->AppID}(读取粉丝信息)\t" . $info, 'red');
                break;
            }

            if (!isset($info['data'])) {
                print_r($info);
            } else {
                foreach ($info['data']['openid'] as $i => $openID) {
                    $fans = $this->load_FansInfo($openID);
                    $ind = $sumFans + $i + 1;
                    $c = $call($fans, "{$ind}/{$info['total']}");
                    if (!$c) continue;
                }
                $sumFans += intval($info['count']);
                if ($sumFans >= intval($info['total'])) break;
            }
            $next = $info['next_openid'] ?? '';
            if (empty($next)) break;
        }
        return $sumFans;
    }

    /**
     * 读取OpenID
     * @param bool|false $getWayOpenID 仅返回OpenID
     * @return array|string
     * http://mp.weixin.qq.com/wiki/17/c0f37d5704f0b64713d5d2c37b468d75.html
     * https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140842
     */
    public function get_OpenID_URL(bool $getWayOpenID = false)
    {
//        $scope = $getUserInfo ? "snsapi_userinfo" : 'snsapi_base';
        $state = md5(date('Ymd') . $this->AppID);    //state标识 重定向后会带上state参数，开发者可以填写a-zA-Z0-9的参数值

        //最后要跳回来的页面
        $uri = _HTTP_ . getenv('HTTP_HOST') . getenv('REQUEST_URI');
        $uri_base = urlencode(base64_encode($uri));

        $param = [];
        $param['appid'] = $this->AppID;
        $param['redirect_uri'] = '';
        $param['response_type'] = 'code';
        $param['scope'] = 'snsapi_base';//"snsapi_userinfo" : 'snsapi_base'
        $param['state'] = $state;

        $isPay = $getWayOpenID ? 1 : 0;

        $time = getenv('REQUEST_TIME_FLOAT') . '.' . mt_rand();
        $sign = md5($this->AppID . $time . 'OPENID');
        if ($this->Platform) {
            if (0) $this->Platform instanceof Platform and 1;
            $param['component_appid'] = $this->Platform->PlatformAppID;
            $url = "{$this->Platform->PlatformURL}/user/openid/{$this->AppID}/{$isPay}/{$uri_base}/{$time}/{$sign}/";
        } else {
            $url = "{$this->mpp['mppDomain']}/login/openid/{$this->AppID}/{$isPay}/{$uri_base}/{$time}/{$sign}/";
        }

        $param['redirect_uri'] = $url;
        $args = http_build_query($param);
        $api = "https://open.weixin.qq.com/connect/oauth2/authorize?{$args}#wechat_redirect";

        $this->debug(['appURL' => $uri, 'redirectURL' => $url, 'redirectAPI' => $api, 'param' => $param]);
        return $api;
    }

    /**
     * @param bool $getWayOpenID
     * @return array|string
     * array:用户信息
     * string:要跳入的URL，或错误信息
     */
    public function load_OpenID(bool $getWayOpenID = false)
    {
        //state标识 重定向后会带上state参数，开发者可以填写a-zA-Z0-9的参数值
        $state = md5(date('Ymd') . $this->AppID);

        if (!isset($_GET['code']) or !isset($_GET['state'])) {

            //最后要跳回来的页面，也就是当前页面
            $uri = _HTTP_ . getenv('HTTP_HOST') . getenv('REQUEST_URI');
            $uri_base = urlencode(base64_encode($uri));
            $isPay = $getWayOpenID ? 1 : 0;

            $param = [];
            $param['appid'] = $this->AppID;
            $param['redirect_uri'] = '';
            $param['response_type'] = 'code';
            $param['scope'] = 'snsapi_base';//"snsapi_userinfo" : 'snsapi_base'
            $param['state'] = $state;

            if ($this->Platform) {
                if (0) $this->Platform instanceof Platform and 1;
                $param['component_appid'] = $this->Platform->PlatformAppID;
                $url = "{$this->Platform->PlatformURL}/user/openid/{$this->AppID}/{$isPay}/{$uri_base}/{$state}/";
            } else {
                $url = "{$this->mpp['mppDomain']}/login/openid/{$this->AppID}/{$isPay}/{$uri_base}/{$state}/";
            }

            $param['redirect_uri'] = $url;
            $args = http_build_query($param);
            $api = "https://open.weixin.qq.com/connect/oauth2/authorize?{$args}#wechat_redirect";
            $this->debug(['appURL' => $uri, 'redirectURL' => $url, 'redirectAPI' => $api, 'param' => $param]);
            return $api;
        }

        /**
         * 进行到这里，这是APP自身进行请求，对于第三方不是在这儿请求
         * 第三方在PlatformURL/user/openid中直接请求，并且会带上openID返回
         */
        if ($_GET['state'] !== $state) return "state与传入值不一致";
        $param = [];
        $param['appid'] = $this->AppID;
        $param['secret'] = $this->mpp['mppSecret'];
        $param['code'] = $_GET['code'];
        $param['grant_type'] = 'authorization_code';
        $args = http_build_query($param);

        $content = $this->Request("/sns/oauth2/access_token?{$args}");
        if (!is_array($content)) return $content;
        if (!isset($content['openid'])) return json_encode($content, 256);
        return $content;
    }

}