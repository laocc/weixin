<?php

namespace laocc\weixin\items;


final class Auth extends Base
{

    /**
     * 三方应用中网站应用，网站登录，第1步：跳转到微信
     * @param array $open
     * @param string $back
     * @param bool $self
     * @return array|string
     */
    final public function authLoginCode(array $open, string $back, bool $self = true)
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
    final public function authGetCodeOpenID(array $app, string $state, string $code, string $back)
    {
        if (empty($state)) return 'StateError';

        //这里的验证，就是验证authLoginCode中的md5结果
        if ($state !== md5($app['openWebAppID'] . date('YmdH') . $back)) return 'TokenError';

        $option = [];
        $option['encode'] = 'json';
        $api = "/sns/oauth2/access_token?appid={$app['openWebAppID']}&secret={$app['openWebSecret']}&code={$code}&grant_type=authorization_code";
        $info = $this->Request($api);
        if (is_string($info)) return $info;

//        $info = Output::request("https://api.weixin.qq.com{$api}", $option);
        if (isset($info['openid'])) {
            //重组最后跳回发起页面，交给控制器跳转
            return sprintf(base64_decode($back), $info['openid'], md5("{$app['openWebAppID']}/{$info['openid']}/signCodeOpenID"));
        }

        return $info['message'];
    }

    /**
     * 最后校验获取到OpenID的URL
     * @param $openID
     * @param $sign
     * @return bool
     */
    final public function authOpenSignCheck(array $app, string $openID, string $sign)
    {
        //这里的sign就是authGetCodeOpenID里最后sprintf中的md5
        if ($sign === md5("{$app['openWebAppID']}/{$openID}/signCodeOpenID")) return true;
        return 'SignError';
    }


}