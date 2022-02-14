<?php

namespace esp\weiXin;

use esp\core\db\ext\RedisHash;
use esp\core\Library;
use esp\http\Http;
use esp\http\HttpResult;
use esp\helper\library\ext\Xml;
use esp\weiXin\platform\Platform;

abstract class Base extends Library
{
    protected $conf;
    protected $AppID;
    protected $Hash;
    protected $redis;
    protected $debug;

    private $path;
    /**
     * @var $_Hash RedisHash
     */
    protected $_Hash;

    public $host = 'https://api.weixin.qq.com';
    public $mpp;
    public $openID;
    public $nick;
    /**
     * @var $Platform Platform
     */
    public $Platform;

    public function _init(array $data)
    {
        $conf = [];
        foreach ($data as $k => $v) {
            if (preg_match('/^[amt]pp[A-Z]\w+/', $k)) $k = substr($k, 3);
            $conf[strtolower($k)] = $v;
        }

        if (empty($conf['appid'])) {
            $this->debug($data);
            throw new \Error("wx conf 至少要含有appid");
        }

        $this->conf = $conf;
        $this->AppID = $conf['appid'];
        $this->_Hash = $this->Hash("aloneMPP");  //整理时可以删除

        if (isset($conf['platform_config'])) {
            $this->Platform = new Platform($conf['platform_config'], $this->AppID);
            unset($conf['platform_config']);
        } else if (isset($data['mppOpenKey']) and $data['mppOpenKey'] !== 'alone') {
            $open = $this->config("open.{$data['mppOpenKey']}");
            $this->Platform = new Platform($open, $this->AppID);
        }

        if (isset($data['mppAppID'])) $this->mpp = $conf;
    }

    /**
     * 切换网站
     *
     * @param array $mpp
     * @return $this
     */
    public function changeMpp(array $mpp)
    {
        $conf = [];
        foreach ($mpp as $k => $v) {
            if (preg_match('/^[amt]pp[A-Z]\w+/', $k)) $k = substr($k, 3);
            $conf[strtolower($k)] = $v;
        }

        $this->mpp = $conf;
        $this->AppID = $conf['appid'];
        $this->conf = ['appid' => $conf['appid'], 'secret' => $conf['secret']];
        return $this;
    }

    /**
     * @param array $xml
     * @param null $notes
     * @return string
     * @throws \Exception
     */
    final public function xml(array $xml, $notes = null)
    {
        return (new Xml($xml, $notes ?: 'xml'))->render(false);
    }

    /**
     * @param string $url
     * @param null $data
     * @param array $option
     * @param null $cert
     * @return array|HttpResult|mixed|null|string
     */
    final public function Request(string $url, $data = null, array $option = [], $cert = null)
    {
        if (empty($url)) return 'empty API';
        if ($data and !isset($option['type'])) $option['type'] = 'post';
        if ($cert and !isset($option['cert'])) $option['cert'] = $cert;
        if (!isset($option['type'])) $option['type'] = 'get';
        if (!isset($option['encode'])) $option['encode'] = 'json';
        if (is_array($data) and $option['type'] !== 'upload') $data = json_encode($data, 256 | 64);

        $hasTry = false;
        tryOnce:
        $api = $url;
        if (strpos($api, '{access_token}')) {
            if ($this->Platform instanceof Platform) {
                $token = $this->Platform->appAccessToken();
            } else {
                $token = $this->load_AccessToken();
            }
            if (is_string($token)) return $token;
            $api = str_replace('{access_token}', $token['token'], $api);
        }
        if ($api[0] !== 'h') $api = "{$this->host}{$api}";

        $request = (new Http($option))->data($data)->request($api);

        $this->debug([$api, $data, $option, $request]);
        if ($err = $request->error()) return $err;

        $error = $request->error();
        if ($error) return $error;

        if ($option['encode'] === 'html') return $request;

        $value = $request->data();
        $check = $this->checkError($value);
        if ($check === 'try_once' and !$hasTry) {
            if (_CLI) echo "{$api}\n";
            $hasTry = true;
            goto tryOnce;
        }

        if (is_string($check)) {
            if ($check === 'try_once') {
                $check = "({$value["errcode"]}){$value["errmsg"]}-{$api}";
            }
            return $check;
        }

        return $value;
    }


    /**
     * 检查信息是否错误
     * @param $inArr
     * @param array $allowCode
     * @return array|mixed|string,
     * array：原样返回，
     * string：具体错误
     * @throws \Exception
     */
    final protected function checkError(array $inArr, array $allowCode = [])
    {
        if (isset($inArr["error"]) && $inArr["error"]) return $inArr['message'];
        if (!isset($inArr["errcode"])) return true;
        $errCode = intval($inArr["errcode"]);

        if ($errCode === 0) {
            return true;

        } else if ($errCode === 40125) {
            $returnInfo = 'AppSecret(应用密钥)被修改，请立即重新配置';
            $this->send_Warnings($returnInfo);
            return $returnInfo;

        } else if ($errCode === 48001) {
            $returnInfo = '当前应用没有此接口权限';
            return $returnInfo;

        } else if ($errCode === 45015) {
            $returnInfo = '此用户与公众号交互时间超过48小时';
            return $returnInfo;

        } else if (in_array($errCode, $allowCode)) {
            //无错，或对于需要返回空值的错误代码
            return true;

        } elseif (in_array($errCode, [41001, 40001, 40014, 42001])) {
            //验证若出错,是否因为Token过期
            $load = $this->load_AccessToken();
            if (is_string($load)) return $load;

            return 'try_once';

        } else {
            return "({$errCode}){$inArr["errmsg"]}";
        }

    }


    /**
     * 下载AccessToken
     * https://mp.weixin.qq.com/wiki/11/0e4b294685f817b95cbed85ba5e82b8f.html
     * @return array|string
     */
    public function load_AccessToken()
    {
        $token = $this->_Hash->get("Access_Token_{$this->AppID}");
        if ($token and $token['expires'] > time()) return $token;

        $api = sprintf("/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s",
            $this->conf['appid'], $this->conf['secret']);
        $dat = $this->Request($api);
        if (is_string($dat)) return $dat;

        $val = ['token' => $dat['access_token'], 'expires' => intval($dat['expires_in']) + time() - 100];
        $this->_Hash->set("Access_Token_{$this->AppID}", $val);

        return $val;
    }

    public function token()
    {
        if ($this->Platform instanceof Platform) {
            return $this->Platform->appAccessToken();
        } else {
            return $this->load_AccessToken();
        }
    }

    /**
     * 读取微信服务器IP地址
     * https://mp.weixin.qq.com/wiki/0/2ad4b6bfd29f30f71d39616c2a0fcedc.html
     * @param array $hack
     * @param bool $isHack
     * @throws \Exception
     */
    public function load_CallBackIP($hack = [], $isHack = false)
    {
        $api = "/cgi-bin/getcallbackip?access_token={access_token}";
        $JsonStr = $this->Request($api);
        if (!$JsonStr['error']) {
            //若需要判断是否黑客，且当前IP不在最新IP中。
            if ($isHack and !in_array(_CIP, $JsonStr['ip_list'])) $hack[] = _CIP;

            $hackIP = json_encode($hack);
            $chatIP = json_encode($JsonStr['ip_list']);
        }
    }


    /**
     * 长链接转短链接
     * http://mp.weixin.qq.com/wiki/10/165c9b15eddcfbd8699ac12b0bd89ae6.html
     * @param $url
     * @return string
     * @throws \Exception
     */
    public function load_ShortUrl($url)
    {
        $api = "/cgi-bin/shorturl?access_token={access_token}";
        $data = [];
        $data['action'] = 'long2short';
        $data['long_url'] = $url;
        $info = $this->Request($api, $data);
        if (is_string($info)) return $info;
        return $info['short_url'];
    }


    public function reVariable($value)
    {
        if (empty($value)) return $value;
        $reJson = false;
        if (is_array($value)) {
            $value = json_encode($value, 256 | 65);
            $reJson = true;
        }

        $key = [];
        $key['AppID'] = $this->AppID;
        $key['AppName'] = $this->mpp['name'];
        $key['RealID'] = $this->mpp['realid'];
        $key['OpenID'] = $this->openID;
        $key['Nick'] = $this->nick;

        $setup = $this->mpp['setup'];
        if (is_string($setup)) $setup = json_decode($setup, true) ?: [];

        if (isset($setup['var'])) {
            $key['ID'] = $setup['var']['id'] ?? '';
            $key['URL'] = $setup['var']['url'] ?? '';
            $key['Domain'] = $setup['var']['domain'] ?? '';
        }

        $value = str_ireplace(array_map(function ($k) {
            return "{{$k}}";
        }, array_keys($key)), array_values($key), $value);
        if ($reJson) return json_decode($value, true);
        return $value;
    }

    public function mppAuth(array $Disable)
    {
        return in_array($this->mpp['type'], $Disable) ? '公众号没有此接口权限' : false;
    }


    /**
     * @param Platform $plat
     * @return $this
     */
    public function changePlat($plat)
    {
        $this->Platform = $plat;
        return $this;
    }


    public function setFans(string $openID, string $nick)
    {
        $this->openID = $openID;
        $this->nick = $nick;
        return $this;
    }

    public function setOpenID(string $OpenID)
    {
        $this->openID = $OpenID;
        return $this;
    }

    public function setNick(string $nick)
    {
        $this->nick = $nick;
        return $this;
    }

    //向管理员报警
    public function send_Warnings($str)
    {
        $msg = [];
        $msg['success'] = false;
        $msg['error'] = $str;
        return $this;
    }


}