<?php

namespace esp\weiXin\items;

use esp\weiXin\platform\Platform;
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
    public function redirect(array $option)
    {
        $backUrl = $option['url'] ?? null;  //最后要跳回来的页面
        if (is_null($backUrl)) $backUrl = _HTTP_ . getenv('HTTP_HOST') . getenv('REQUEST_URI');
        $uri_base = urlencode(base64_encode($backUrl));

        $param = [];
        $param['appid'] = $this->AppID;
        $param['redirect_uri'] = '';
        $param['response_type'] = 'code';
        $param['scope'] = ($option['scope'] ?? 'snsapi_base');//"snsapi_userinfo" : 'snsapi_base'
        $param['state'] = $this->sign_url();

        $isPay = ($option['pay'] ?? 0) ? 1 : 0;

        $time = getenv('REQUEST_TIME_FLOAT') . '.' . mt_rand();
        $sign = md5($this->AppID . $time . 'OPENID');
        if ($this->Platform) {
            $param['component_appid'] = $this->Platform->PlatformAppID;
            $url = "{$this->Platform->PlatformURL}/user/openid/{$this->AppID}/{$isPay}/{$uri_base}/{$time}/{$sign}/";
        } else {
            $url = "{$this->mpp['domain']}/login/openid/{$this->AppID}/{$isPay}/{$uri_base}/{$time}/{$sign}/";
        }

//        $param['redirect_uri'] = $url;
        $param['redirect_uri'] = $backUrl;
        $args = http_build_query($param);
        $api = "https://open.weixin.qq.com/connect/oauth2/authorize?{$args}#wechat_redirect";

        $this->debug(['appURL' => $backUrl, 'redirectURL' => $url, 'redirectAPI' => $api, 'param' => $param]);
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() - 1) . ' GMT');
        header("Cache-Control: no-cache");
        header("Pragma: no-cache");
        header("Location: {$api}");
        fastcgi_finish_request();
        exit;
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

    /**
     * @param array $option
     * @return array|string
     * @throws Exception
     */
    public function load_OpenID(array $option)
    {
        if (!isset($_GET['code']) or !isset($_GET['state'])) $this->redirect($option);

        if (!$this->sign_url($_GET['state'])) return "state与传入值不一致";

        if ($this->Platform) return $this->Platform->loadOpenID();

        $param = [];
        $param['appid'] = $this->AppID;
        $param['secret'] = $this->mpp['secret'];
        $param['code'] = $_GET['code'];
        $param['grant_type'] = 'authorization_code';
        $args = http_build_query($param);

        $content = $this->Request("/sns/oauth2/access_token?{$args}");
        if (!is_array($content)) return $content;
        if (!isset($content['openid'])) return json_encode($content, 256);
        return $content;
    }

}