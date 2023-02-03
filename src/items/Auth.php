<?php

namespace esp\weiXin\items;

/**
 * 微信开放平台的网站应用，授权网站登录
 */
final class Auth
{
    private string $appid;
    private string $secret;
    private string $token;

    public function __construct(string $appid, string $secret, string $token = __FILE__)
    {
        $this->appid = $appid;
        $this->secret = $secret;
        $this->token = $token;
    }

    /**
     * 模式一：直接跳转
     *
     * @param string $backUrl
     * @param int $mode
     * 1：当前窗口跳转，无1为top跳转
     * 2：直接返回URL，而不是直接跳走
     * @return string|void
     */
    public function redirect(string $backUrl, int $mode = 0)
    {
        $state = md5($this->appid . date('YmdH') . $this->token);
        $self = ($mode & 1) ? 'true' : 'false';
        $api = "https://open.weixin.qq.com/connect/qrconnect?appid=%s&self_redirect=%s&redirect_uri=%s&response_type=code&scope=snsapi_login&state=%s#wechat_redirect";
        $api = sprintf($api, $this->appid, $self ? 'true' : 'false', urlencode($backUrl), $state);
        if ($mode & 2) return $api;

        header('Expires: ' . gmdate('D, d M Y H:i:s', time() - 1) . ' GMT');
        header("Cache-Control: no-cache");
        header("Pragma: no-cache");
        header("Location: {$api}");
        fastcgi_finish_request();
        exit;
    }

    /**
     * 模式二：在当前页面获取json
     *
     * @param string $backUrl
     * @param string $divID
     * @return false|string
     */
    public function json(string $backUrl, string $divID = 'auth')
    {
        $state = md5($this->appid . date('YmdH') . $this->token);
        $js = [
            'self_redirect' => true,
            'id' => $divID,
            'appid' => $this->appid,
            'scope' => 'snsapi_login',
            'redirect_uri' => urlencode($backUrl),
            'state' => $state,
            'style' => 'white',
            'href' => '',
        ];
        return json_encode($js, 320);
    }

    /**
     * 前两种模式中$backUrl页面中调用此方法，获取OpenID
     *
     * @param string $openID
     * @return string|null
     */
    public function openid(string &$error = null): ?string
    {
        $code = $_GET['code'] ?? '';
        $state = $_GET['state'] ?? '';
        $sign = md5($this->appid . date('YmdH') . __FILE__);
        if ($sign !== $state) {
            $error = '授权码已失效，请重新生成登录码';
            return null;
        }

        $api = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=%s&secret=%s&code=%s&grant_type=authorization_code';
        $json = file_get_contents(sprintf($api, $this->appid, $this->secret, $code));

        $json = json_decode($json, true) ?: [];
        if (empty($json)) {
            $error = '换取access_token失败，本次登录失败';
            return null;
        }
        if ($json['errcode'] ?? '') {
            $error = $json['errmsg'];
            return null;
        }

        $openID = $json['openid'] ?? '';
        if (!$openID) {
            $error = '登录异常';
            return null;
        }

        return $openID;
    }

    /**
     * 以下为原始老版本代码，因兼容问题，暂不能删除，请不要用
     *
     *
     * 三方应用中网站应用，网站登录，第1步：跳转到微信
     * @param array $open
     * @param string $back
     * @param bool $self
     * @return array|string
     */
    public function authLoginCode(array $open, string $back, bool $self = true)
    {
        $uri = sprintf($open['openWebApi'], $back);
        $state = md5($open['openWebAppID'] . date('YmdH') . $back);
        if (!$self) return ['uri' => $uri, 'state' => $state, 'appid' => $open['openWebAppID']];
        $self = $self ? 'true' : 'false';
        $uri = base64_encode($uri);
        $api = "/connect/qrconnect?appid={$open['openWebAppID']}&self_redirect={$self}&redirect_uri={$uri}&response_type=code&scope=snsapi_login&state={$state}#wechat_redirect";
        return "https://open.weixin.qq.com{$api}";
    }


    /**
     * 公共API收到数据，验证后，用Code换取access_token及openID
     * @param array $app
     * @param string $state
     * @param string $code
     * @param string $back
     * @return string
     */
    public function authGetCodeOpenID(array $app, string $state, string $code, string $back): string
    {
        if (empty($state)) return 'StateError';

        //这里的验证，就是验证authLoginCode中的md5结果
        if ($state !== md5($app['openWebAppID'] . date('YmdH') . $back)) return 'TokenError';

        $api = "/sns/oauth2/access_token?appid={$app['openWebAppID']}&secret={$app['openWebSecret']}&code={$code}&grant_type=authorization_code";
        $info = file_get_contents($api);
        $info = json_decode($info, true) ?: [];
        if (empty($info)) return '请求异常';

        if (isset($info['openid'])) {
            //重组最后跳回发起页面，交给控制器跳转
            return sprintf(base64_decode($back), $info['openid'], md5("{$app['openWebAppID']}/{$info['openid']}/signCodeOpenID"));
        }

        return $info['message'];
    }

    /**
     * 最后校验获取到OpenID的URL
     *
     * @param array $app
     * @param string $openID
     * @param string $sign
     * @return bool|string
     */
    public function authOpenSignCheck(array $app, string $openID, string $sign)
    {
        //这里的sign就是authGetCodeOpenID里最后sprintf中的md5
        if ($sign === md5("{$app['openWebAppID']}/{$openID}/signCodeOpenID")) return true;
        return 'SignError';
    }


}