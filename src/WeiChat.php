<?php

namespace esp\weiXin;

use \esp\weiXin\app\App;
use \esp\weiXin\items\Api;
use \esp\weiXin\items\Auth;
use \esp\weiXin\items\Custom;
use \esp\weiXin\items\Fans;
use \esp\weiXin\items\Push;
use \esp\weiXin\items\Reply;
use \esp\weiXin\items\Media;
use \esp\weiXin\items\Menu;
use \esp\weiXin\items\Tag;
use \esp\weiXin\items\Template;
use \esp\weiXin\items\Pay;

abstract class WeiChat
{

    public static function App(array $conf)
    {
        return new App($conf);
    }

    public static function Pay(array $conf)
    {
        return new Pay($conf);
    }

    public static function Reply(array $conf)
    {
        return new Reply($conf);
    }

    public static function Custom(array $conf)
    {
        return new Custom($conf);
    }

    public static function Push(array $conf)
    {
        return new Push($conf);
    }

    public static function Media(array $conf)
    {
        return new Media($conf);
    }

    public static function Fans(array $conf)
    {
        return new Fans($conf);
    }

    public static function Menu(array $conf)
    {
        return new Menu($conf);
    }

    public static function Tag(array $conf)
    {
        return new Tag($conf);
    }

    public static function Api(array $conf)
    {
        return new Api($conf);
    }

    public static function Auth(array $conf)
    {
        return new Auth($conf);
    }

    public static function Template(array $conf)
    {
        return new Template($conf);
    }


}