<?php

namespace esp\weixin;

use \esp\weixin\items\Api;
use esp\weixin\items\App;
use \esp\weixin\items\Auth;
use \esp\weixin\items\Custom;
use \esp\weixin\items\Fans;
use \esp\weixin\items\Push;
use \esp\weixin\items\Reply;
use \esp\weixin\items\Media;
use \esp\weixin\items\Menu;
use \esp\weixin\items\Tag;
use \esp\weixin\items\Template;
use \esp\weixin\items\Pay;

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