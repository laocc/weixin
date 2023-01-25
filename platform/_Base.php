<?php

namespace esp\weiXin\platform;

use esp\core\Library;
use esp\dbs\redis\Redis;
use esp\http\Http;
use esp\weiXin\Hash;
use Exception;

class _Base extends Library
{
    const wxApi = 'https://api.weixin.qq.com';

    public string $AppID;

    public string $PlatformAppID;
    public string $PlatformURL;
    protected string $PlatformToken;
    protected string $PlatformEncodingAESKey;
    protected string $PlatformAppSecret;

    protected Hash $_Hash;

    public function _init(array $open, string $AppID = '')
    {
        $this->PlatformAppID = $open['appid'];
        $this->PlatformToken = $open['token'];
        $this->PlatformEncodingAESKey = $open['aeskey'];
        $this->PlatformAppSecret = $open['secret'];
        $this->PlatformURL = $open['host'];
        $this->AppID = $AppID;  //公众号的APPID

        if (_CLI) {
            //cli中config的redis不可靠，需要重新创建
            $rds = new Redis($this->_controller->_config->_redis_conf);
            $this->_Hash = new Hash($rds->redis, "PLAT_{$this->PlatformAppID}");
        } else {
            $this->_Hash = new Hash($this->_controller->_redis, "PLAT_{$this->PlatformAppID}");
        }

    }


    /**
     * 切换AppID
     * @param string $AppID
     * @return $this
     */
    public function reAppID(string $AppID)
    {
        $this->AppID = $AppID;
        return $this;
    }

    public function token(string $name, $value = null)
    {
        if (is_null($value)) {
            $val = $this->_Hash->get("{$name}_{$this->AppID}");
            if ($val) return $val;
            return null;
        }
        return $this->_Hash->set("{$name}_{$this->AppID}", $value);
    }

    /**
     * 2、获取第三方平台.component_access_token
     * @param bool $byHash
     * @return mixed
     */
    public function PlatformAccessToken(bool $byHash = true)
    {
        $time = time();
        $token = $this->_Hash->get("Access_Token");
        if ($byHash and is_array($token)) {
            if (intval($token['expires'] ?? 0) > $time) return $token['token'];
        }

        $Ticket = $this->_Hash->get('Verify_Ticket');
        if (empty($Ticket)) {
            $fil = _RUNTIME . "/Verify_Ticket_{$this->PlatformAppID}";
            if (is_file($fil)) {
                $Ticket = file_get_contents($fil);
                $this->_Hash->set('Verify_Ticket', $Ticket);
            } else {
                throw new Exception("{$this->PlatformAppID} Verify_Ticket 丢失");
            }
        }

        $data = [
            'component_appid' => $this->PlatformAppID,
            'component_appsecret' => $this->PlatformAppSecret,
            'component_verify_ticket' => $Ticket,
        ];

        $api = '/cgi-bin/component/api_component_token';
        $value = $this->Request($api, $data);
        if (is_string($value)) {
            print_r($data);
            throw new Exception($value, 500);
        }

        $code = ['token' => $value['component_access_token'], 'time' => date('Y-m-d H:i:s', $time), 'expires' => $time + intval($value['expires_in']) - 100];
        $this->_Hash->set("Access_Token", $code);
        return $code['token'];
    }

    /**
     * @param $url
     * @param null $data
     * @param array $option
     * @param null $cert
     * @return array|mixed|string
     */
    public function Request($url, $data = null, array $option = [], $cert = null)
    {
        $option += ['type' => 'get', 'encode' => 'json'];
        if ($data) $option['type'] = 'post';
        if ($cert) $option['cert'] = $cert;
        if (empty($url)) return 'empty API';

        $hasTry = false;
        tryOnce:
        $api = $url;

        if (strpos($api, '{component_access_token}')) {
            $token = $this->PlatformAccessToken();
            if (!$token) return 'empty AccessToken';
            $api = str_replace('{component_access_token}', $token, $api);

        } else if (strpos($api, '{authorizer_access_token}')) {
            $token = $this->appAccessToken();
            if (!$token) return 'empty AccessToken';
            $api = str_replace('{authorizer_access_token}', $token['token'], $api);
        }

        if ($api[0] === '/') $api = $this::wxApi . $api;

        $postVal = (new Http($option))->data($data)->post($api);
        $this->debug($postVal);

        $value = $postVal->data();

        $this->debug(['api' => $api, 'data' => $data, 'value' => $value], 1);

        $check = $this->checkError($value, $option['allow'] ?? []);
        if ($check === 'try_once' and !$hasTry) {
            $hasTry = true;
            goto tryOnce;
        }
        if (is_string($check)) return $check;

        return $value;
    }

    /**
     * 5、获取（刷新）授权公众号或小程序的接口调用凭据（令牌）
     * 该API用于在授权方令牌（authorizer_access_token）失效时，可用刷新令牌（authorizer_refresh_token）获取新的令牌。
     * 请注意，此处token是2小时刷新一次，开发者需要自行进行token的缓存，避免token的获取次数达到每日的限定额度。
     */
    public function appAccessToken()
    {
        $token = $this->token("AccessToken");
        if (!empty($token) and $token['expires'] > time()) return $token;

        $refresh = $this->token("RefreshToken");
        if (empty($refresh['token'])) {
            //如果RefreshToken丢失，则从三方平台接口中查询
            $refTKn = $this->getAllAuthorizerMppApp($this->AppID);
            if (!is_array($refTKn)) return "mpp({$this->AppID}) Get Refresh Token Error";
            if (!isset($refTKn['refresh_token'])) return "该账号授权异常";
            $this->token("RefreshToken", ['token' => $refTKn['refresh_token'], 'time' => time()]);
            $refresh = [];
            $refresh['token'] = $refTKn['refresh_token'];
        }

        $data = [];
        $data['component_appid'] = $this->PlatformAppID;
        $data['authorizer_appid'] = $this->AppID;
        $data['authorizer_refresh_token'] = $refresh['token'];

        $api = "/cgi-bin/component/api_authorizer_token?component_access_token={component_access_token}";
        $value = $this->Request($api, $data);
        if (is_string($value)) return $value;
        if (!isset($value['authorizer_access_token'])) {
            print_r($data);
            print_r($value);
            return 'get authorizer access token Error';
        }

        $token = ['token' => $value['authorizer_access_token'], 'expires' => time() + intval($value['expires_in']) - 100];
        $this->token("AccessToken", $token);
        if (isset($value['authorizer_refresh_token']) and !empty($value['authorizer_refresh_token'])) {
            $this->token("RefreshToken", ['token' => $value['authorizer_refresh_token'], 'time' => time()]);
        }

        return $token;
    }

    /**
     * 所有已授权的公众号和小程序
     *
     * @param string|null $appID
     * @return array|mixed|string
     */
    public function getAllAuthorizerMppApp(string $appID = null)
    {
        $this->debug($this->AppID);
        $all = $this->listApp();
        if (is_null($appID)) return $all;
        foreach ($all as $app) {
            if ($app['authorizer_appid'] === $appID) return $app;
        }
        return '';
    }


    public function listApp(): array
    {
        $api = "/cgi-bin/component/api_get_authorizer_list?component_access_token={component_access_token}";
        $data = [];
        $data['component_appid'] = $this->PlatformAppID;
        $data['offset'] = 0;
        $data['count'] = 500;
        $appList = [];
        while (1) {
            $value = $this->Request($api, $data);
            if (is_string($value)) break;
            array_push($appList, ...$value['list']);
            if (count($value['list']) < 500) break;
        }
        return $appList;
    }


    /**
     * 检查信息是否错误
     * @param $inArr
     * @param array $allowCode
     * @return array|mixed|string,
     * array：原样返回，
     * string：具体错误
     */
    private function checkError($inArr, array $allowCode = [])
    {
        if ($inArr["error"] ?? '') return $inArr['message'] ?? $inArr["error"];
        if (!isset($inArr["errcode"])) return $inArr;

        $errCode = intval($inArr["errcode"] ?? 0);

        if ($errCode === 0) {
            return $inArr;

        } else if ($allowCode === ['all']) {
            return $inArr;

        } else if (in_array($errCode, $allowCode)) {
            //无错，或对于需要返回空值的错误代码
            return $inArr;

        } else {
            return "({$errCode})" . ($inArr["errmsg"] ?? '');
        }
    }

}