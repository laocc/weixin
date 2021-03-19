<?php

namespace esp\weiXin;

use \esp\weiXin\items\Api;
use \esp\weiXin\items\App;
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

    public function Custom(array $conf)
    {
        return new Custom($conf);
    }

    public function Push(array $conf)
    {
        return new Push($conf);
    }

    public function Media(array $conf)
    {
        return new Media($conf);
    }

    public function Fans(array $conf)
    {
        return new Fans($conf);
    }

    public function Menu(array $conf)
    {
        return new Menu($conf);
    }

    public function Tag(array $conf)
    {
        return new Tag($conf);
    }

    public function Api(array $conf)
    {
        return new Api($conf);
    }

    public function Auth(array $conf)
    {
        return new Auth($conf);
    }

    public function Template(array $conf)
    {
        return new Template($conf);
    }


}