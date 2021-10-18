<?php

namespace demo;

use esp\weiXin\items\Fans;
use esp\weiXin\platform\Platform;

/**
 * 获取用户openID
 */
const open_conf = [
    'appid' => '开放平台AppID',
    'secret' => '开放平台Secret',
    'aeskey' => '',
    'token' => '',
    'host' => 'https://是绑定在开放平台的域名',
];

function openidGet()
{
    $plat = new Platform(open_conf);

    $app = [];
    $app['appid'] = '已授权给开放平台第三方账号的公众号AppID';
    $fans = new Fans($app);
    $fans->changePlat($plat);

    $option = [];

    //需要在三方平台域名下实现这个控制器
    $option['platform'] = '/user/openid';

    /**
     * 跳转最终要跳回来的页面，一般这就是当前URL，但在有些情况下需要特意指定
     * 比如在nginx中做了域名转发的情况下
     * 当前域名：web.host.com
     * 在nginx中将HOST改成了 app.host.com
     * 这时 getenv('HTTP_HOST')的值将是 app.host.com
     * 但实际是需要回到 web.host.com
     * 所以这时要手工指定跳回来的URL
     */
    $option['url'] = _HTTP_ . getenv('HTTP_HOST') . getenv('REQUEST_URI');

    //"snsapi_userinfo" : 'snsapi_base'，默认snsapi_base
    $option['scope'] = 'snsapi_base';

    $option['pay'] = 0;

    $fans = $fans->load_OpenID($option);

    return $fans['openid'];
}

/**
 * 三方平台域名下，也就是上面$option['platform'] = '/user/openid';
 *
 * @return array|bool|mixed|string|null
 */
function openidAction()
{
    $plat = new Platform(open_conf);
    return $plat->loadOpenID();
}