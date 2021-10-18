<?php

namespace esp\weiXin\items;

use esp\weiXin\Base;
use Exception;

final class Fans extends Base
{

    /**
     * 读取某一个用户
     * https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140839
     * @param $OpenID
     * @return array|mixed|string
     * @throws Exception
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
     * @throws Exception
     * https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1421140840
     */
    public function load_AllFans(callable $call, string $next = ''): int
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
     * 读取OpenID前跳转
     *
     * @param array $option
     */
    public function redirectWeixin(array $option)
    {
        $backUrl = $option['url'] ?? null;  //最后要跳回来的页面
        if (is_null($backUrl)) $backUrl = _HTTP_ . getenv('HTTP_HOST') . getenv('REQUEST_URI');

        $param = [];
        $param['appid'] = $this->AppID;
        $param['redirect_uri'] = '';
        $param['response_type'] = 'code';
        $param['scope'] = ($option['scope'] ?? 'snsapi_base');//"snsapi_userinfo" : 'snsapi_base'
        $param['state'] = $this->sign_url();
        $param['redirect_uri'] = $backUrl;

        if ($this->Platform) {
            $data = [
                'appid' => $this->AppID,
                'pay' => $option['pay'] ?? 0,
                'back' => $backUrl,
                'key' => $this->openIdKey,
                '_' => mt_rand(),
            ];
            $data = urlencode(base64_encode(gzcompress(json_encode($data, 320), 5)));
            $sign = md5($this->AppID . $data . 'OPENID');
            //需在第三方平台所绑定的域名下实现下列URI，两个参数
            $platUri = rtrim($option['platform'] ?? '/fans/openid', '/');
            $pam = ['data' => $data, 'sign' => $sign];
            $fh = strpos($platUri, '?') ? '&' : '?';
            $param['redirect_uri'] = "{$this->Platform->PlatformURL}{$platUri}{$fh}" . http_build_query($pam);
            $param['component_appid'] = $this->Platform->PlatformAppID;
        }

        $args = http_build_query($param);
        $api = "https://open.weixin.qq.com/connect/oauth2/authorize?{$args}#wechat_redirect";

        $this->debug(['appURL' => $backUrl, 'redirectAPI' => $api, 'param' => $param]);
        $this->redirect($api);
    }

    private $openIdKey = '_openid_';

    /**
     * @param array $option
     * @return array|string
     *
     * 两种情况：
     * 1，由第三方平台受理；
     * 2，直连微信公众号；
     *
     */
    public function load_OpenID(array $option = [])
    {
        /**
         * 原始页面，获取到三方平台跳回来时所带的openID
         */
        if (isset($_GET[$this->openIdKey])) {
            $openID = @gzuncompress(base64_decode(urldecode($_GET[$this->openIdKey])));
            if (!$openID) return 'fail uri';
            $sign = md5($this->openIdKey . '=' . $openID . 'OpenID' . date('Ymd'));
            $str = ($_GET["{$this->openIdKey}sign"] ?? 'e');
            if ($sign !== $str) {
                $sign = md5($this->openIdKey . '=' . $openID . 'OpenID' . date('Ymd', time() - 5));
                if ($sign !== $str) $this->redirectWeixin($option);
            }
            return ['openid' => $openID];
        }

        if (!isset($_GET['code']) or !isset($_GET['state'])) $this->redirectWeixin($option);

        if (!$this->sign_url($_GET['state'])) return "state与传入值不一致";

        //直连微信公众号平台时，处理回传的数据
        $param = [];
        $param['appid'] = $this->AppID;
        $param['secret'] = $this->mpp['secret'];
        $param['code'] = $_GET['code'];
        $param['grant_type'] = 'authorization_code';
        $args = http_build_query($param);

        $content = $this->Request("/sns/oauth2/access_token?{$args}");
        if (!is_array($content)) return $content;
        if (!isset($content['openid'])) return json_encode($content, 256);

        /**
         * 这时$content里已带有openid
         */
        return $content;
    }

    /**
     * @param string|null $state
     * @return bool|string
     */
    private function sign_url(string $state = null)
    {
        $token = md5(date('Ymd') . $this->AppID);
        if (is_null($state)) return $token;

        return $token === $state;
    }

}