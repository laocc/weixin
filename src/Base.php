<?php

namespace esp\weiXin;

use esp\core\Library;
use esp\dbs\redis\Redis;
use esp\helper\library\Error;
use esp\http\Http;
use esp\http\HttpResult;
use esp\helper\library\ext\Xml;
use esp\weiXin\platform\Platform;
use Exception;

abstract class Base extends Library
{
    const wxApi = 'https://api.weixin.qq.com';

    protected $AppID;
    protected $mpp;
    protected $openID;
    protected $nick;

    /**
     * @var $Platform Platform
     */
    protected $Platform;

    /**
     * @var $_Hash Hash
     */
    protected $_Hash;


    /**
     * @param array $data
     * @throws Error
     */
    public function _init(array $data)
    {
        $conf = [];
        foreach ($data as $k => $v) {
            if (preg_match('/^[amt]pp[A-Z]\w+/', $k)) $k = substr($k, 3);
            $conf[strtolower($k)] = $v;
        }

        if (!isset($conf['appid']) or empty($conf['appid'])) {
            $this->debug($data);
            throw new \Error("wx conf 至少要含有appid");
        }

        $this->mpp = $conf;
        $this->AppID = $conf['appid'];//当前公众号或小程序的appid

        if (isset($conf['platform_config'])) {
            $this->Platform = new Platform($conf['platform_config'], $this->AppID);
            unset($conf['platform_config']);

        } else if (isset($data['mppOpenKey']) and $data['mppOpenKey'] !== 'alone') {
            $open = $this->config("open.{$data['mppOpenKey']}");
            $this->Platform = new Platform($open, $this->AppID);

        } else if (!isset($conf['secret']) or empty($conf['secret'])) {
            $this->debug($data);
            throw new \Error("wx conf 自主接入的应用至少要含有secret");
        }
    }

    protected function Hash()
    {
        if (_CLI) {
            $conf = $this->config('database.redis');
            $rds = new Redis($conf);
            return new Hash($rds->redis, 'aloneMPP');
        } else {
            return new Hash($this->_controller->_redis, 'aloneMPP');
        }
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
        return $this;
    }


    /**
     * @param Platform $plat
     * @return $this
     */
    public function changePlat(Platform $plat)
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

    /**
     * @param array $xml
     * @param null $notes
     * @return string
     * @throws Exception
     */
    public function xml(array $xml, $notes = null)
    {
        return (new Xml($xml, $notes ?: 'xml'))->render(false);
    }

    /**
     * @param string $url
     * @param null $data
     * @param array $option
     * @param null $cert
     * @return array|HttpResult|mixed|null|string
     * @throws Exception
     */
    public function Request(string $url, $data = null, array $option = [], $cert = null)
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
        if ($api[0] === '/') $api = $this::wxApi . $api;

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
     *
     * @param array $inArr
     * @param array $allowCode
     * @return bool|mixed|string
     * @throws Exception
     */
    protected function checkError(array $inArr, array $allowCode = [])
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
            return '当前应用没有此接口权限';

        } else if ($errCode === 45015) {
            return '此用户与公众号交互时间超过48小时';

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
     * @throws Exception
     */
    public function load_AccessToken()
    {
        $token = $this->Hash()->get("Access_Token_{$this->AppID}");
        if ($token) {
            if (is_string($token)) $token = unserialize($token);
            if ($token['expires'] > time()) return $token;
        }

        $api = sprintf("/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s", $this->mpp['appid'], $this->mpp['secret']);
        $dat = $this->Request($api);
        if (is_string($dat)) return $dat;

        $val = ['token' => $dat['access_token'], 'expires' => intval($dat['expires_in']) + time() - 100];
        $this->Hash()->set("Access_Token_{$this->AppID}", $val);

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
     * @throws Exception
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
     * @throws Exception
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

    //向管理员报警
    public function send_Warnings($str)
    {
        $msg = [];
        $msg['success'] = false;
        $msg['error'] = $str;
        return $this;
    }


}