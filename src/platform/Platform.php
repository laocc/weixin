<?php

namespace esp\weiXin\platform;

use esp\core\db\Redis;
use esp\core\Model;
use esp\http\Http;
use esp\library\request\Get;
use esp\weiXin\auth\Crypt;

final class Platform extends Model
{
    public $_table = 'Platform';
    public $_id = 'ID';

    private $api = 'https://api.weixin.qq.com';
    public $AppID;
    public $AppAdminID;

    public $PlatformAppID;
    public $PlatformToken;
    public $PlatformEncodingAESKey;
    public $PlatformURL;
    private $PlatformAppSecret;

    private $Hash;
    private $Temp;

    public function _init(array $open, string $AppID = '')
    {
        $this->PlatformAppID = $open['appid'];
        $this->PlatformToken = $open['token'];
        $this->PlatformEncodingAESKey = $open['aeskey'];
        $this->PlatformAppSecret = $open['secret'];
        $this->PlatformURL = $open['host'];

        $conf = $this->_config->get('database.redis');
        $redis = new Redis($conf);

        $this->Hash = $redis->hash("PLAT_{$open['appid']}");  //整理时可以删除
        $this->Temp = $redis->hash("Temp_" . date('Ymd'));      //第二天后可以删除

        $this->AppID = $AppID;  //公众号的APPID
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
            $val = $this->Hash->get("{$name}_{$this->AppID}");
            if ($val) return $val;
            return null;
        }
        return $this->Hash->set("{$name}_{$this->AppID}", $value);
    }

    public function Open()
    {
        return new Open($this);
    }

    /**
     * 授权接入跳转目标URL
     * 3、获取预授权码pre_auth_code
     * 这里生成的链接，由站长微信扫码，引导进入授权页面
     *
     * $mppID用在两个地方：
     * 1，授权成功后手机端跳入的页面中
     * 2，放在Temp里，和预授权码放一起，授权成功后，根据授权码确定是哪个应用
     *
     * 授权结束后：
     * 1，手机跳入/mpp/accessGet($openAppID, $mppID)，这里的$mppID暂时没有用
     * 2，后台通知到/mpp/openPost，即本类->acceptGrantEvent
     *
     * @param $adminID
     * @param $type
     * @return array|string
     * @throws \Exception
     */
    public function CreateAccessGrantCode(int $adminID, int $type = 1)
    {
        $data = ['component_appid' => $this->PlatformAppID];
        $api = '/cgi-bin/component/api_create_preauthcode?component_access_token={component_access_token}';
        $value = $this->Request($api, $data);

        //预授权码有效期为10分钟
        $code = ['code' => $value['pre_auth_code'], 'adminID' => $adminID];
        $this->Temp->set(md5($value['pre_auth_code']), $code);

        $back = urlencode(sprintf("%s/mpp/access/%s/%s/", $this->PlatformURL, $this->PlatformAppID, $adminID));
        $url = "https://mp.weixin.qq.com/safe/bindcomponent?action=bindcomponent&auth_type=%s&no_scan=1&component_appid=%s&pre_auth_code=%s&redirect_uri=%s#wechat_redirect";
        return sprintf($url, $type, $this->PlatformAppID, $value['pre_auth_code'], $back);
    }

    /**
     * 授权接入URL
     * @param $appID
     * @param $true
     * @return array|string
     * @throws \Exception
     * /?auth_code=queryauthcode@@@9dTChTMS0-dL2InzvcyY5UU4slUyAfNRNZQ7UzouB_0S_XP_PY6D_HphnrzkezgW3yMXHumkhhKXPZHajYEU2Q&expires_in=3600
     */
    /**
     * @param $code
     * @param $express
     * @return array
     */
    public function accessGrant($code, $express)
    {
        //有效期为1小时
        $auth = [
            'code' => $code,
            'expires' => time() + intval($express) - 100
        ];
        $this->token("AuthorizationCode", $auth);
        return $auth;
    }

    public function getPlatformTicket($PlatformAppID)
    {
        $Ticket = '';
        $fil = _RUNTIME . "/Verify_Ticket_{$PlatformAppID}";
        if (is_file($fil)) $Ticket = file_get_contents($fil);
        return $Ticket;
    }

    /**
     * 2、获取第三方平台.component_access_token
     * @return mixed
     * @throws \Exception
     */
    public function PlatformAccessToken(bool $byHash = true)
    {
        $time = time();
        $token = $this->Hash->get("Access_Token");
        if ($byHash and is_array($token)) {
            if (intval($token['expires'] ?? 0) > $time) return $token['token'];
        }

        $Ticket = $this->Hash->get('Verify_Ticket');
        if (empty($Ticket)) {
            $fil = _RUNTIME . "/Verify_Ticket_{$this->PlatformAppID}";
            if (is_file($fil)) {
                $Ticket = file_get_contents($fil);
                $this->Hash->set('Verify_Ticket', $Ticket);
            } else {
                throw new \Exception("{$this->PlatformAppID} Verify_Ticket 丢失");
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
            throw new \Exception($value, 500);
        }

        $code = ['token' => $value['component_access_token'], 'time' => date('Y-m-d H:i:s', $time), 'expires' => $time + intval($value['expires_in']) - 100];
        $this->Hash->set("Access_Token", $code);
        return $code['token'];
    }


    /**
     * 4、使用授权码换取公众号或小程序的接口调用凭据和授权信息
     * @param string $AuthorizationCode
     * @param int $adminID
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    public function getAuthInfo(string $AuthorizationCode)
    {
        $data = [];
        $data['component_appid'] = $this->PlatformAppID;
        $data['authorization_code'] = $AuthorizationCode;

        //拉取授权基本信息
        $api = "/cgi-bin/component/api_query_auth?component_access_token={component_access_token}";
        $value = $this->Request($api, $data);
        if (is_string($value)) return $value;

        if (isset($value['errcode']) and $value['errcode'] > 0) {
            return $value['errmsg'];
        }
        if (!isset($value['authorization_info'])) return 'empty authorization_info';


        $info = $value['authorization_info'];
        $this->debug($info);

        if (isset($info['authorizer_access_token']) and !empty($info['authorizer_access_token'])) {
            $this->token("AccessToken", ['token' => $info['authorizer_access_token'], 'expires' => time() + intval($info['expires_in']) - 100]);
        }

        if (isset($info['authorizer_refresh_token']) and !empty($info['authorizer_refresh_token'])) {
            $this->token("RefreshToken", ['token' => $info['authorizer_refresh_token'], 'time' => time()]);
        }

        $func = array_column($info['func_info'], 'funcscope_category');
        $func = array_column($func, 'id');
        $auth = [];
        $auth['func'] = $func;

        $minAuth = [1, 2, 3, 4, 7, 11, 15];
        $result = array_intersect($minAuth, $func);
        if ($result !== $minAuth) $auth['warn'] = '授权不完整';
        return $auth;
    }

    /**
     * 6、获取授权方的帐号基本信息
     */
    public function getAppInfo()
    {
        $api = "/cgi-bin/component/api_get_authorizer_info?component_access_token={component_access_token}";
        $data = [];
        $data['component_appid'] = $this->PlatformAppID;
        $data['authorizer_appid'] = $this->AppID;

        $value = $this->Request($api, $data);
        $this->debug($value);
        if (is_string($value)) return $value;

        if (isset($value['errcode']) and $value['errcode'] > 0) {
            return $value['errmsg'];
        }

        $auth = [];
        $getApi = true;
        $appInfo = $value['authorizer_info'];
        $authInfo = $value['authorization_info'];
        $isMiniApp = isset($appInfo['MiniProgramInfo']);

        if (isset($authInfo['authorizer_refresh_token'])) {
            if (empty($authInfo['authorizer_refresh_token'])) {
                //无值，即无API权限
//                $auth['warn'][] = '无API权限';
                $getApi = false;
            } else {
                $this->token("RefreshToken", ['token' => $authInfo['authorizer_refresh_token'], 'time' => time()]);
            }
        } else {
            $auth['warn'] = '无API权限';
            $getApi = false;
        }

        $func = array_column($authInfo['func_info'], 'funcscope_category');
        $func = array_column($func, 'id');
        $auth['func'] = $func;

        //[1,15,4,7,2,11,6,9,10]}
        $minAuth = [1, 2, 3, 4, 7, 11, 15];
        $result = array_intersect($minAuth, $func);
        if (!$isMiniApp and $result !== $minAuth) $auth['warn'][] = '授权不完整';

        $update = [];
        $update['mppOpenAuth'] = json_encode($auth, 256);
        $update['mpQrCode'] = $appInfo['qrcode_url'];

        if ($isMiniApp) {
            $update['mppType'] = intval($appInfo['service_type_info']['id']);
            if ($update['mppType'] === 0) $update['mppType'] = 16;//普通小程序
            else if ($update['mppType'] === 12) $update['mppType'] = 64;//试用小程序
            if (intval($appInfo['verify_type_info']['id']) === 0) $update['mppType'] *= 2;

        } else {
            $update['mppType'] = intval($appInfo['service_type_info']['id']);
            if ($update['mppType'] === 0) $update['mppType'] = 1;//订阅号，未认证
            else if ($update['mppType'] === 2) $update['mppType'] = 4;//服务号，未认证
            if (intval($appInfo['verify_type_info']['id']) === 0) $update['mppType'] *= 2;
        }

        $update['mppAppID'] = $authInfo['authorizer_appid'];
        $update['mppRealID'] = $appInfo['user_name'];
        $update['mppName'] = $appInfo['nick_name'];
        $update['mppCompanyName'] = $appInfo['principal_name'];
        $update['mppUserName'] = $appInfo['alias'];
        $update['mppOpenKey'] = $this->PlatformAppID;

        return $update;
        /**
         * 读取并转换站点的基础二维码
         * $appInfo['qrcode_url']
         */
    }

    /**
     * 5、获取（刷新）授权公众号或小程序的接口调用凭据（令牌）
     * 该API用于在授权方令牌（authorizer_access_token）失效时，可用刷新令牌（authorizer_refresh_token）获取新的令牌。
     * 请注意，此处token是2小时刷新一次，开发者需要自行进行token的缓存，避免token的获取次数达到每日的限定额度。
     */
    public function appAccessToken()
    {
        $test = 0;
        $token = $this->token("AccessToken");
        if (!empty($token) and $token['expires'] > time()) return $token;

        $refresh = $this->token("RefreshToken");
        if (empty($refresh['token'])) {
            //如果RefreshToken丢失，则从三方平台接口中查询
            $v = $this->getRefreshToken();
            if (!is_array($v)) return "mpp({$this->AppID}) Get Refresh Token Error";
            if (!isset($v['refresh_token'])) return "该账号授权异常";
            $this->token("RefreshToken", ['token' => $v['refresh_token'], 'time' => time()]);
            $refresh = [];
            $refresh['token'] = $v['refresh_token'];
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
     * 7、获取授权方的选项设置信息
     */
    public function getAppOption($option)
    {
        $api = "/cgi-bin/component/api_get_authorizer_option?component_access_token={component_access_token}";
        $data = [];
        $data['component_appid'] = $this->PlatformAppID;
        $data['authorizer_appid'] = $this->AppID;

        $option = $this->option($option);
        if (empty($option)) return "非法Option";
        $data['option_name'] = $option['key'];

        $value = $this->Request($api, $data);

        return $value;
    }

    /**
     * 地理位置上报，语音识别开关，多客服开关
     * @param $key
     * @return array|mixed
     */
    private function option($key)
    {
        $val = [];
        $val['location'] = ['key' => 'location_report', 'val' => [0 => '无上报', 1 => '进入会话时上报', 2 => '每5s上报']];//地理位置上报选项
        $val['voice'] = ['key' => 'voice_recognize', 'val' => [0 => '关闭', 1 => '开启']];//语音识别开关选项
        $val['customer'] = ['key' => 'customer_service', 'val' => [0 => '关闭', 1 => '开启']];//多客服开关选项
        return $val[$key] ?? [];
    }

    /**
     * 8、设置授权方的选项信息
     */
    public function setAppOption($option, $value)
    {
        $api = "/cgi-bin/component/api_set_authorizer_option?component_access_token={component_access_token}";
        $data = [];
        $data['component_appid'] = $this->PlatformAppID;
        $data['authorizer_appid'] = $this->AppID;
        $option = $this->option($option);
        if (empty($option)) return "非法Option";
        if (isset($option['val'][$value])) return "非法OptionValue";

        $data['option_name'] = $option['key'];
        $data['option_value'] = $value;

        $value = $this->Request($api, $data);

        return $value;
    }

    public function listApp()
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

    private function getRefreshToken()
    {
        $this->debug($this->AppID);
        $all = $this->listApp();
        if (!is_array($all)) return '';
        foreach ($all as $app) {
            if ($app['authorizer_appid'] === $this->AppID) return $app;
        }
        return '';
    }


    /**
     * 9、推送授权相关通知
     * 1，在第三方平台创建审核通过后，微信服务器会向其“授权事件接收URL”每隔10分钟定时推送verify_ticket。
     * 2，当公众号对第三方平台进行授权、取消授权、更新授权后，微信服务器会向第三方平台方的授权事件接收URL（创建第三方平台时填写）推送相关通知。
     * @return string
     * @throws \Exception
     */
    public function acceptGrantEvent()
    {
        $input = file_get_contents("php://input");
        if (empty($input)) return 'null';
        $debug = $this->debug($input);
        $get = new Get();

        $crypt = new Crypt($this->PlatformAppID, $this->PlatformToken, $this->PlatformEncodingAESKey);
        $data = $crypt->decode($input, $get['msg_signature'], $get['timestamp'], $get['nonce']);

        if (!is_null($debug)) {
            $this->debug($data);
            if (isset($data['AuthorizerAppid'])) {
                $debug->path("{$data['AuthorizerAppid']}/{$data['InfoType']}");
            } else {
                $debug->path("{$data['InfoType']}");
            }
        }

        switch ($data['InfoType']) {
            case 'component_verify_ticket'://1、推送verify_ticket协议
                $this->Hash->set('Verify_Ticket', $data['ComponentVerifyTicket']);
                file_put_contents(_RUNTIME . "/Verify_Ticket_{$data['AppId']}", $data['ComponentVerifyTicket']);
                break;

            case 'authorized'://授权成功通知
            case 'updateauthorized'://授权更新通知

                $this->AppID = $data['AuthorizerAppid'];
                $code = $this->Temp->get(md5($data['PreAuthCode']));
                if (empty($code)) break;

                $this->AppAdminID = intval($code['adminID'] ?? 0);

                $this->token("AuthorizationCode", [
                    'code' => $data['AuthorizationCode'],
                    'expires' => intval($data['AuthorizationCodeExpiredTime']) - 100
                ]);

                if ($data['InfoType'] === 'authorized') {
                    $this->_buffer->publish('order', 'asyncMpp', ['_action' => 'asyncMpp', 'mppAppID' => $this->AppID]);
                }

                break;
            case 'unauthorized'://取消授权通知
                $this->AppID = $data['AuthorizerAppid'];
                break;
            default:
        }

        return $data['InfoType'];
    }

    final protected function task(string $action, $value)
    {
        return $this->_buffer->publish('order', $action, ['_action' => $action] + $value);
    }

    public function loadOpenID()
    {
        if (!isset($_GET['code'])) return null;

        $code = $_GET['code'];

        //若最近缓存过，直接返回
        $check = $this->Temp->get($code);
        if (is_array($check)) {
            $this->debug($check);
            return $check;
        }

        $token = $this->PlatformAccessToken();
        if (!$token) return 'empty AccessToken';

        $hasTry = false;
        tryGet:
        $param = [];
        $param['appid'] = $this->AppID;
        $param['code'] = $_GET['code'];
        $param['grant_type'] = 'authorization_code';
        $param['component_appid'] = $this->PlatformAppID;
        $param['component_access_token'] = $token;
        $args = http_build_query($param);

        $content = $this->Request("/sns/oauth2/component/access_token?{$args}");
        if (!is_array($content)) return $content;
        if (!isset($content['openid'])) {
            if (!$hasTry and isset($content['errmsg']) and strpos($content['errmsg'], 'access_token is invalid or not latest') > 0) {
                $token = $this->PlatformAccessToken(false);
                $hasTry = true;
                goto tryGet;
            }
            return json_encode($content, 256);
        }

        $content['time'] = time();
        $content['url'] = _URL;
        $content['ip'] = _CIP;
        $content['agent'] = getenv('HTTP_USER_AGENT');
        $this->Temp->set($code, $content);

        return $content;
    }

    /**
     * @param $url
     * @param null $data
     * @param array $option
     * @param null $cert
     * @return array|mixed|string
     * @throws \Exception
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
        if ($api[0] !== 'h') $api = "{$this->api}{$api}";

        $postVal = (new Http($option))->data($data)->post($api);
        $this->debug($postVal);

        $value = $postVal->data();

        $prev = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $this->debug(['api' => $api, 'data' => $data, 'value' => $value], $prev);

        $check = $this->checkError($value, $option['allow'] ?? []);
        if ($check === 'try_once' and !$hasTry) {
            $hasTry = true;
            goto tryOnce;
        }
        if (is_string($check)) return $check;

        return $value;
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

        } else if (in_array($errCode, $allowCode)) {
            //无错，或对于需要返回空值的错误代码
            return $inArr;

        } else {
            return "({$errCode})" . ($inArr["errmsg"] ?? '');
        }
    }


}