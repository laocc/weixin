<?php

namespace esp\weiXin;

use esp\dbs\redis\RedisHash;
use esp\error\Error;
use esp\core\Library;
use esp\dbs\redis\Redis;
use esp\http\Http;
use esp\http\HttpResult;
use esp\helper\library\ext\Xml;
use esp\weiXin\platform\Platform;
use Exception;

abstract class Base extends Library
{
    const wxApi = 'https://api.weixin.qq.com';

    protected array $mpp;
    protected string $AppID;
    protected string $openID = '';
    protected string $nick = '';
    protected string $platAccessToken;
    protected string $appAccessToken;
    private bool $returnBase = false;
    private bool $saveDebug = false;

    protected Platform $Platform;
//    protected Hash $_Hash;
    protected RedisHash $_Hash;
    protected Redis $_Redis;


    /**
     * @param array $data
     * @param Platform|null $platform
     * @throws Error
     */
    public function _init(array $data, Platform $platform = null)
    {
        $conf = [];
        foreach ($data as $k => $v) {
            if (preg_match('/^[amt]pp[A-Z]\w+/', $k)) $k = substr($k, 3);
            $conf[strtolower($k)] = $v;
        }

        if (!isset($conf['appid']) or empty($conf['appid'])) {
            if (!_CLI) $this->debug($data);
            throw new Error("wx conf 至少要含有appid");
        }

        $this->mpp = $conf;
        $this->AppID = $conf['appid'];//当前公众号或小程序的appid

        if ($platform instanceof Platform) {
            $this->Platform = $platform;

        } else if (isset($conf['platform_config'])) {
            $this->Platform = new Platform($conf['platform_config'], $this->AppID);
            unset($conf['platform_config']);

        } else if (isset($data['mppOpenKey']) and $data['mppOpenKey'] !== 'alone') {
            throw new Error("wx conf 传入mppOpenKey的方式已禁用，请直接传入完整的platform_config");

        } else if (!isset($conf['secret']) or empty($conf['secret'])) {
            if (!_CLI) $this->debug($data);
            throw new Error("wx conf 自主接入的应用至少要含有secret");
        }
    }

    public function setRedis(Redis $redis): Base
    {
        $this->_Redis = $redis;
        return $this;
    }

    public function setReturnBase(bool $set): Base
    {
        $this->returnBase = $set;
        return $this;
    }

    public function setSaveDebug(bool $set): Base
    {
        $this->saveDebug = $set;
        return $this;
    }

    /**
     * _CLI模式下，建议先传入redis以创建hash
     *
     * @param Redis|null $redis
     * @return RedisHash
     * @throws Error
     */
    public function Hash(Redis $redis = null): RedisHash
    {
        if (isset($this->_Hash)) return $this->_Hash;

        if (!is_null($redis)) {
            $rds =& $redis->redis;

        } else if (isset($this->_Redis)) {
            $rds =& $this->_Redis->redis;

        } else if (_CLI) {            //cli中config的redis不可靠，需要重新创建
            $newRedis = new Redis($this->_controller->_config->_redis_conf);
            $rds =& $newRedis->redis;

        } else {
            $rds =& $this->_controller->_redis;
        }

        $this->_Hash = new RedisHash($rds, 'aloneMPP');
        return $this->_Hash;
    }

    /**
     * 切换网站
     *
     * @param array $mpp
     * @return $this
     */
    public function changeMpp(array $mpp): Base
    {
        $conf = [];
        foreach ($mpp as $k => $v) {
            if (preg_match('/^[amt]pp[A-Z]\w+/', $k)) $k = substr($k, 3);
            $conf[strtolower($k)] = $v;
        }

        $this->mpp = $conf;
        $this->AppID = $conf['appid'];
//        $this->secret = $conf['secret'];
        return $this;
    }


    /**
     * @param Platform $plat
     * @return $this
     */
    public function changePlat(Platform $plat): Base
    {
        $this->Platform = $plat;
        return $this;
    }

    public function setFans(string $openID, string $nick): Base
    {
        $this->openID = $openID;
        $this->nick = $nick;
        return $this;
    }

    public function setOpenID(string $OpenID): Base
    {
        $this->openID = $OpenID;
        return $this;
    }

    public function setNick(string $nick): Base
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
    public function xml(array $xml, $notes = null): string
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
        if (!isset($option['decode'])) $option['decode'] = $option['encode'];
        if (is_array($data) and $option['type'] !== 'upload') $data = json_encode($data, 256 | 64);

        $hasTry = false;
        tryOnce:
        $api = $url;
        if (strpos($api, '{access_token}')) {
            $api = str_replace('{access_token}', $this->token(), $api);
        }
        if ($api[0] === '/') $api = $this::wxApi . $api;

        $http = new Http($option);
        $request = $http->data($data)->request($api);
        if ($this->returnBase) return $request;
        if (!_CLI and $this->saveDebug or isset($option['debug'])) {
            $this->debug([$api, $data, $option, $request]);
        }
        if ($err = $request->error()) return $err;

        if (($option['decode'] === 'html') or ($option['decode'] === 'buffer')) return $request;

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

        if (intval($value['errcode'] ?? '') !== 0) return $value['errmsg'];
        if (isset($value['errmsg']) and strtolower($value['errmsg']) !== 'ok') return $value['errmsg'];

        return $value;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function token(): string
    {
        if (isset($this->Platform)) {
            $token = $this->Platform->appAccessToken();
        } else {
            $token = $this->load_AccessToken();
        }

        $this->debug($token);
        if (is_string($token)) return $token;

        return $token['token'];
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
            return $this->send_Warnings('AppSecret(应用密钥)被修改，请立即重新配置');

        } else if ($errCode === 48001) {
            return '当前应用没有此接口权限';

        } else if ($errCode === 45015) {
            return '此用户与公众号交互时间超过48小时';

        } else if ($allowCode === ['all']) {
            return true;

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
//            if (_CLI) print_r($token);
            if ($token['expires'] > time()) return $token;
        }

        $api = sprintf("/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s", $this->mpp['appid'], $this->mpp['secret']);
        $dat = $this->Request($api);
        if (is_string($dat)) return $dat;

        $expire = intval($dat['expires_in']) + time() - 100;
        $val = [
            'token' => $dat['access_token'],
            'expires' => $expire,
            'datetime' => date('Y-m-d H:i:s', $expire),
        ];
        if (_CLI) print_r($val);
        $this->Hash()->set("Access_Token_{$this->AppID}", $val);

        return $val;
    }

    public function load_stableAccessToken()
    {
        $token = $this->Hash()->get("Access_Token_{$this->AppID}");
        if ($token) {
            if (is_string($token)) $token = unserialize($token);
            if ($token['expires'] > time()) return $token;
        }

        $post = [
            'grant_type' => 'client_credential',
            'appid' => $this->mpp['appid'],
            'secret' => $this->mpp['secret'],
            'force_refresh' => false,
        ];
        $dat = $this->Request('/cgi-bin/stable_token', $post);
        if (is_string($dat)) return $dat;

        $expire = intval($dat['expires_in']) + time() - 100;
        $val = [
            'token' => $dat['access_token'],
            'expires' => $expire,
            'datetime' => date('Y-m-d H:i:s', $expire),
        ];
        $this->Hash()->set("Access_Token_{$this->AppID}", $val);

        return $val;
    }

    /**
     * 读取微信服务器IP地址
     * https://mp.weixin.qq.com/wiki/0/2ad4b6bfd29f30f71d39616c2a0fcedc.html
     * @param array $hack
     * @param bool $isHack
     * @throws Exception
     */
    public function load_CallBackIP($hack = [], bool $isHack = false)
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
    public function load_ShortUrl($url): string
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
     * 向管理员报警
     *
     * @param string $str
     * @return array
     */
    public function send_Warnings(string $str): array
    {
        $msg = [];
        $msg['success'] = false;
        $msg['error'] = $str;

        return $msg;
    }


}