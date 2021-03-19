<?php

namespace esp\weiXin;

use esp\core\Debug;
use esp\http\Http;
use esp\http\Result;
use esp\library\ext\Xml;

abstract class Base
{
    protected $conf;
    protected $AppID;

    private $path;

    public $host = 'https://api.weixin.qq.com';

    public $mpp;
    public $OpenID;
    public $Nick;

    public $Platform;

    protected $Hash;
    protected $redis;

    public function __construct(array $conf)
    {
        if (!isset($conf['appid']) or empty($conf['appid'])) {
            throw new \Error("wx conf 至少要含有appid:" . json_encode($conf, 256 | 64));
        }
        $this->path = "/tmp/wx/{$conf['appid']}";
        if (!is_dir($this->path)) mkdir($this->path, 0740, true);
        $this->conf = $conf;
        $this->AppID = $conf['appid'];
    }


    /**
     * @param string $name
     * @param null $value
     * @return array|int|string
     * @throws \Exception
     *
     * 目前只用于存储：
     * Access_Token：自主接入时，此值为单独获取，如果是授权接入，则由platform读取
     * ApiTicket：
     *
     */
    protected function tempCache(string $name, $value = null)
    {
        if (is_null($value)) {
            if (!is_readable("{$this->path}/{$name}")) return null;
            $txt = file_get_contents("{$this->path}/{$name}");
            return unserialize($txt);
        }
        return file_put_contents("{$this->path}/{$name}", serialize($value)) > 0;
    }


    /**
     * @param $val
     * @param null $prev
     * @return Debug|bool
     */
    public function debug($val, $prev = null)
    {
        return null;
        $debug = Debug::class();
        if (is_null($debug)) return false;
        if (is_null($val)) return $debug;
        $prev = is_null($prev) ? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0] : $prev;
        return $debug->relay($val, $prev);
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
     * @param $url
     * @param null $data
     * @param array $option
     * @param null $cert
     * @return array|mixed|string|Result
     * @throws \Exception
     */
    final public function Request(string $url, $data = null, array $option = [], $cert = null)
    {
        if (empty($url)) return 'empty API';
        if ($data and !isset($option['type'])) $option['type'] = 'post';
        if ($cert and !isset($option['cert'])) $option['cert'] = $cert;
        if (!isset($option['type'])) $option['type'] = 'get';
        if (!isset($option['encode'])) $option['encode'] = 'json';

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

        $request = (new Http($option))->data($data)->post($api);

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
     * @throws \Exception
     */
    public function load_AccessToken()
    {
        $token = $this->tempCache('Access_Token_V');
        if ($token and $token['expires'] > time()) return $token;

        $api = sprintf("/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s",
            $this->conf['appid'], $this->conf['secret']);
        $dat = $this->Request($api);
        if (is_string($dat)) return $dat;

        $val = ['token' => $dat['access_token'], 'expires' => intval($dat['expires_in']) + time() - 100];
        $this->tempCache('Access_Token_V', $val);

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
     * 生成二维码
     * 临时码的scene_id为32位非0整形，也就是小于：4,294,967,295
     *
     * @param string $scene
     * @param int $expire
     * @return array|mixed|string
     * https://mp.weixin.qq.com/wiki?t=resource/res_main&id=mp1443433542
     */
    /**
     * @param int $id
     * @param string $str
     * @param int $expire
     * @return mixed
     * @throws \Exception
     */
    public function load_QrTick(int $id, string $str, int $expire = 0)
    {
        //若$expire小于30，则该值表示的为天，转换为秒
        if ($expire > 0 and $expire <= 30) $expire = $expire * 86400;

        //$expire不可超过30天有效期
        if ($expire > 2592000) $expire = 2592000;

        /**
         * 二维码类型，
         * QR_SCENE:            为临时的整型参数值，
         * QR_STR_SCENE:        为临时的字符串参数值，
         * QR_LIMIT_SCENE:      为永久的整型参数值，
         * QR_LIMIT_STR_SCENE:  为永久的字符串参数值
         */
        $data = [];
        if ($expire > 0) {//临时码
            $data['expire_seconds'] = $expire;
            if (empty($str)) {
                $data['action_name'] = 'QR_SCENE';
                $data['action_info'] = ['scene' => ['scene_id' => $id]];
            } else {
                $data['action_name'] = 'QR_STR_SCENE';
                $data['action_info'] = ['scene' => ['scene_str' => $str]];
            }

        } else { //永久码
            $data['action_name'] = 'QR_LIMIT_STR_SCENE';
            $data['action_info'] = ['scene' => ['scene_id' => $id, 'scene_str' => $str]];
        }

        $api = "/cgi-bin/qrcode/create?access_token={access_token}";
        $JsonStr = $this->Request($api, $data);
        return $JsonStr;
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
        if (!is_string($value)) return $value;
        $reJson = false;
        if (is_array($value)) {
            $value = json_encode($value, 256 | 65);
            $reJson = true;
        }

        $key = [];
        $key['AppID'] = $this->AppID;
        $key['AppName'] = $this->mpp['mppName'];
        $key['RealID'] = $this->mpp['mppRealID'];
        $key['OpenID'] = $this->OpenID;
        $key['Nick'] = $this->Nick;

        $setup = $this->mpp['mppSetup'];
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
        return in_array($this->mpp['mppType'], $Disable) ? '公众号没有此接口权限' : false;
    }


    /**
     * 后台群发中心切换网站
     * @param array $mpp
     * @return $this
     * @throws \Exception
     */
    public function changeMpp(array $mpp)
    {
        $this->mpp = $mpp;
        $this->AppID = $mpp['mppAppID'];
        return $this;
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

    public function setOpenID($OpenID)
    {
        $this->OpenID = $OpenID;
        return $this;
    }

    public function setNick($nick)
    {
        $this->Nick = $nick;
        return $this;
    }

    //向管理员报警
    public function send_Warnings($str)
    {
        $msg = [];
        $msg['success'] = false;
        $msg['error'] = $str;
//        echo json_encode($msg, 256);
//        exit();
    }


}