<?php

namespace esp\weiXin;

use \esp\weiXin\app\App;

use \esp\weiXin\items\Api;
use \esp\weiXin\items\Auth;
use \esp\weiXin\items\Fans;
use \esp\weiXin\items\Reply;
use \esp\weiXin\items\Media;
use \esp\weiXin\items\Menu;
use \esp\weiXin\items\Tag;
use \esp\weiXin\items\Pay;

use \esp\weiXin\send\Custom;
use \esp\weiXin\send\Push;
use \esp\weiXin\send\Template;

abstract class WeiChat
{

    public static function App(array $conf): App
    {
        return new App($conf);
    }

    public static function Pay(array $conf): Pay
    {
        return new Pay($conf);
    }

    public static function Reply(array $conf): Reply
    {
        return new Reply($conf);
    }

    public static function Custom(array $conf): Custom
    {
        return new Custom($conf);
    }

    public static function Push(array $conf): Push
    {
        return new Push($conf);
    }

    public static function Media(array $conf): Media
    {
        return new Media($conf);
    }

    public static function Fans(array $conf): Fans
    {
        return new Fans($conf);
    }

    public static function Menu(array $conf): Menu
    {
        return new Menu($conf);
    }

    public static function Tag(array $conf): Tag
    {
        return new Tag($conf);
    }

    public static function Api(array $conf): Api
    {
        return new Api($conf);
    }

    public static function Auth(array $conf): Auth
    {
        return new Auth($conf['appid'], $conf['secret']);
    }

    public static function Template(array $conf): Template
    {
        return new Template($conf);
    }


}