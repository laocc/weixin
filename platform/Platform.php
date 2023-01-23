<?php

namespace esp\weiXin\platform;

use esp\weiXin\auth\Crypt;
use Exception;

class Platform extends _Base
{

    /**
     * @return Open
     */
    public function Open(): Open
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
     * @param int $adminID
     * @param int $type
     * @return string
     */
    public function CreateAccessGrantCode(int $adminID, int $type = 1): string
    {
        $data = ['component_appid' => $this->PlatformAppID];
        $api = '/cgi-bin/component/api_create_preauthcode?component_access_token={component_access_token}';
        $value = $this->Request($api, $data);

        //预授权码有效期为10分钟
        $code = ['code' => $value['pre_auth_code'], 'adminID' => $adminID];
        $this->_Hash->set(md5($value['pre_auth_code']), $code);

        $back = urlencode(sprintf("%s/mpp/access/%s/%s/", $this->PlatformURL, $this->PlatformAppID, $adminID));
        $url = "https://mp.weixin.qq.com/safe/bindcomponent?action=bindcomponent&auth_type=%s&no_scan=1&component_appid=%s&pre_auth_code=%s&redirect_uri=%s#wechat_redirect";
        return sprintf($url, $type, $this->PlatformAppID, $value['pre_auth_code'], $back);
    }



    /**
     * 授权接入URL
     * @param $appID
     * @param $true
     * @return array|string
     * @throws Exception
     * /?auth_code=queryauthcode@@@9dTChTMS0-dL2InzvcyY5UU4slUyAfNRNZQ7UzouB_0S_XP_PY6D_HphnrzkezgW3yMXHumkhhKXPZHajYEU2Q&expires_in=3600
     */
    /**
     * @param $code
     * @param $express
     * @return array
     */
    public function accessGrant($code, $express): array
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
     * 4、使用授权码换取公众号或小程序的接口调用凭据和授权信息
     * @param string $AuthorizationCode
     * @return array|bool|mixed|string
     * @throws Exception
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
     * 7、获取授权方的选项设置信息
     * @param $option
     * @return array|mixed|string
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

        return $this->Request($api, $data);
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
     * @param $option
     * @param $value
     * @return array|mixed|string
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

        return $this->Request($api, $data);
    }

    /**
     * 9、推送授权相关通知
     * 1，在第三方平台创建审核通过后，微信服务器会向其“授权事件接收URL”每隔10分钟定时推送verify_ticket。
     * 2，当公众号对第三方平台进行授权、取消授权、更新授权后，微信服务器会向第三方平台方的授权事件接收URL（创建第三方平台时填写）推送相关通知。
     * @return string|array
     * @throws Exception
     */
    public function acceptGrantEvent(string $input = null, array $get = [])
    {
        if (empty($input)) $input = file_get_contents("php://input");
        if (empty($input)) return 'null';
        if (empty($get)) $get = $_GET;
        $debug = $this->debug([$input, $get]);

        $crypt = new Crypt($this->PlatformAppID, $this->PlatformToken, $this->PlatformEncodingAESKey);
        $data = $crypt->decode($input, $get['msg_signature'] ?? '', $get['timestamp'] ?? '', $get['nonce'] ?? '');

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
                $this->_Hash->set('Verify_Ticket', $data['ComponentVerifyTicket']);
                file_put_contents(_RUNTIME . "/Verify_Ticket_{$data['AppId']}", $data['ComponentVerifyTicket']);
                break;

            case 'authorized'://授权成功通知
            case 'updateauthorized'://授权更新通知

                $this->AppID = $data['AuthorizerAppid'];
                $code = $this->_Hash->get(md5($data['PreAuthCode']));
                if (empty($code)) break;

                $data['AppAdminID'] = intval($code['adminID'] ?? 0);

                $this->token("AuthorizationCode", [
                    'code' => $data['AuthorizationCode'],
                    'expires' => intval($data['AuthorizationCodeExpiredTime']) - 100
                ]);

                if ($data['InfoType'] === 'authorized') {
                    $this->_controller->publish('asyncMpp', ['_action' => 'asyncMpp', 'mppAppID' => $this->AppID]);
                }

                break;
            case 'unauthorized'://取消授权通知
                $this->AppID = $data['AuthorizerAppid'];
                break;
            case 'notify_third_fasteregister'://快速注册小程序
                if (isset($data['appid'])) $this->AppID = $data['appid'];
                break;
            default:
        }

        return $data;
    }

    /**
     * @return string|array
     *
     * 这里的数据是Fans中组合的，签名规则也是在Fans()->redirectWeixin()中创建的
     *
     */
    private function checkOpenIDData()
    {
        $data = $_GET['data'] ?? '';
        $sign = $_GET['sign'] ?? '';
        if (empty($data) or empty($sign)) return '三方平台返回Data错误';
        $array = json_decode(@gzuncompress(base64_decode(urldecode($data))), true);
        if (empty($array)) return '三方平台返回Data错误';
        $str = md5($array['appid'] . $data . 'OPENID');
        if ($str !== $sign) return '三方平台返回URL签名错误';

        return $array;
    }

    /**
     * 三方平台，受理微信跳回来的数据
     *
     * @return array|false|mixed|string|null
     */
    public function loadOpenID()
    {
        if (!isset($_GET['code'])) return null;

        $array = $this->checkOpenIDData();
        if (is_string($array)) return $array;
        $this->debug($array);
        $fh = strpos($array['back'], '?') ? '&' : '?';

        $code = $_GET['code'];

        //若最近缓存过，直接返回
        $check = $this->_Hash->get($code);
        if (is_array($check)) {
            $this->debug($check);
            $openID = urlencode(base64_encode(gzcompress($check['openid'], 5)));
            $sign = md5($array['key'] . '=' . $check['openid'] . 'OpenID' . date('Ymd'));
            $this->redirect("{$array['back']}{$fh}{$array['key']}={$openID}&{$array['key']}sign={$sign}");
            return true;
        }

        $token = $this->PlatformAccessToken();
        if (!$token) return 'empty AccessToken';

        $hasTry = false;
        tryGet:
        $param = [];
        $param['appid'] = $array['appid'];
        $param['code'] = $_GET['code'];
        $param['grant_type'] = 'authorization_code';
        $param['component_appid'] = $this->PlatformAppID;
        $param['component_access_token'] = $token;
        $args = http_build_query($param);

        $content = $this->Request("/sns/oauth2/component/access_token?{$args}");
        $this->debug($content);
        if (!is_array($content)) return $content;

        if (!isset($content['openid'])) {
            if (!$hasTry and isset($content['errmsg']) and strpos($content['errmsg'], 'access_token is invalid or not latest') > 0) {
                $token = $this->PlatformAccessToken(false);
                $hasTry = true;
                goto tryGet;
            }
            return json_encode($content, 320);
        }
        $this->_Hash->set($code, $content);
        $openID = urlencode(base64_encode(gzcompress($content['openid'], 5)));
        $sign = md5($array['key'] . '=' . $content['openid'] . 'OpenID' . date('Ymd'));
        $redirect = "{$array['back']}{$fh}{$array['key']}={$openID}&{$array['key']}sign={$sign}";
        $this->redirect($redirect);
        return true;
    }


}