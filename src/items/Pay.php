<?php

namespace esp\weixin\items;

use function esp\helper\str_rand;
use esp\weixin\Base;

final class Pay extends Base
{


    public function refundPay(array $payment)
    {

        if ($payment['notify_url'][0] === '/') $payment['notify_url'] = _HTTP_ . "api." . _HOST . $payment['notify_url'];


        $payInfo = array();
        $payInfo['appid'] = $this->conf['appid'];
        $payInfo['mch_id'] = $this->conf['mchid'];
        $payInfo['nonce_str'] = str_rand(15);
        $payInfo['sign_type'] = 'MD5';
        $payInfo['transaction_id'] = $payment['transaction_id'];
        $payInfo['out_trade_no'] = $payment['out_trade_no'];
        $payInfo['out_refund_no'] = $payment['out_refund_no'];
        $payInfo['total_fee'] = $payment['total_fee'];
        $payInfo['refund_fee'] = $payment['refund_fee'];
        $payInfo['refund_desc'] = $payment['refund_desc'];
        $payInfo['notify_url'] = $payment['notify_url'];
        $payInfo['refund_fee_type'] = 'CNY';
        $payInfo['sign'] = $this->createSign($payInfo, $this->conf['key']);//签名，详见签名生成算法

        $payInfoXml = $this->xml($payInfo);
        $this->debug([$this->conf, $payInfo, $payInfoXml]);

        $api = 'https://api.mch.weixin.qq.com/secapi/pay/refund';
        $option = [];
        $option['encode'] = 'xml';
        $option['type'] = 'post';
        $option['cert'] = $this->conf['cert'];
//        $option['cert'] = ['cert' => $config['cert.cert'], 'key' => $config['cert.key']];

        $content = $this->Request($api, $payInfoXml, $option);
        $err = "订单退款:";

        if (!is_array($content)) return "{$err}{$content}";
        if ($content['return_code'] !== 'SUCCESS') return "{$err}{$content['return_msg']}";        //生成支付时错误
        if ($content['result_code'] !== 'SUCCESS') return "{$err}{$content['err_code_des']}";        //生成支付时错误
        if (!$this->checkSign($content, $this->conf['key'])) return "{$err}返回签名错误";

        return $content;
    }

    /**
     * 解密退款的回报数据
     * @param string $code
     * @return string
     */
    public function deCodeCryptRefund(string $code)
    {
        return openssl_decrypt(base64_decode($code), "AES-256-ECB", md5($this->conf['key']), OPENSSL_RAW_DATA);
    }


    /**
     * 生成模式一的二维支付，此二维码永久有效
     */
    public function createQrCode($shopID)
    {
        $val = [];
        $val['appid'] = $this->AppID;//微信号
        $val['mch_id'] = '';//商户号
        $val['product_id'] = $shopID;//商家ID
        $val['time_stamp'] = time();
        $val['nonce_str'] = str_rand(32);
        $val['sign'] = $this->createSign($val, '');
        $url = 'weixin://wxpay/bizpayurl?' . http_build_query($val);
        //weixin://wxpay/bizpayurl?appid=wx75ff6dd72d5edfcd&mch_id=1446344302&product_id=10&time_stamp=1489919446&nonce_str=93nuLPaVvrwBZ5pUFEGlO2xcyeK4TMSo&sign=24F6731E3C0D203F0474DD5C9FEC501F
        //转换成短连接
        return $url;
    }

    /**
     * 二维码收款
     * @param array $data
     * @return array|string
     */
    public function getCodePay(array $data)
    {
        $unified = $this->UnifiedOrder('NATIVE', $data);//统一下单
        if (is_string($unified)) {
            $this->debug(['NATIVE.统一下单异常：', $data])->error($unified);
            return $unified;
        }

        $return = [];
        $return['return_code'] = 'SUCCESS';
        $return['result_code'] = 'SUCCESS';
        $return['appid'] = $this->conf['appid'];
        $return['mch_id'] = $this->conf['mchid'];
        $return['nonce_str'] = str_rand(32);
        $return['prepay_id'] = $unified['prepay_id'];
        return $return;

        /**
         * 名称    变量名    类型    必填    示例值    描述
         * 返回状态码    return_code    String(16)    是    SUCCESS    SUCCESS/FAIL,此字段是通信标识，非交易标识，交易是否成功需要查看result_code来判断
         * 返回信息    return_msg    String(128)    否    签名失败    返回信息，如非空，为错误原因;签名失败;具体某个参数格式校验错误.
         * 公众账号ID    appid    String(32)    是    wx8888888888888888    微信分配的公众账号ID
         * 商户号    mch_id    String(32)    是    1900000109    微信支付分配的商户号
         * 随机字符串    nonce_str    String(32)    是    5K8264ILTKCH16CQ2502SI8ZNMTM67VS    微信返回的随机字符串
         * 预支付ID    prepay_id    String(64)    是    wx201410272009395522657a690389285100    调用统一下单接口生成的预支付ID
         * 业务结果    result_code    String(16)    是    SUCCESS    SUCCESS/FAIL
         * 错误描述    err_code_des    String(128)    否        当result_code为FAIL时，商户展示给用户的错误提
         * 签名    sign    String(32)    是    C380BEC2BFD727A4B6845133519F3AD6    返回数据签名，签名生成算法
         */

    }


    /**
     * H5调起支付API
     * @param array $data
     * @param array $config
     * @return array|bool|mixed|string
     */
    public function getJsApiPay(array $data, array $config)
    {
        $unified = $this->UnifiedOrder('JSAPI', $data, $config);//统一下单
        if (is_string($unified)) {
            $this->debug(['JSAPI.统一下单异常：', $data, $config])->error($unified);
            return $unified;
        }

        $values = array();
        $values['appId'] = $config['appid'];
        $values['timeStamp'] = strval($data['time']);//这timeStamp中间的S必须是大写
        $values['nonceStr'] = str_rand(30);//随机字符串，不长于32位。推荐随机数生成算法
        $values['package'] = "prepay_id={$unified['prepay_id']}";
        $values['signType'] = 'MD5';
        $values['paySign'] = $this->createSign($values, $config['token']);//生成签名
        return $values;
    }

    public function getH5Pay(array $data, array $config)
    {
        $unified = $this->UnifiedOrder('MWEB', $data, $config);//统一下单
        if (is_string($unified)) {
            $this->debug(['MWEB.统一下单异常：', $data, $config])->error($unified);
            return $unified;
        }

        $values = array();
        $values['appid'] = $config['appid'];
        $values['partnerid'] = $config['mchid'];//商户号
        $values['prepayid'] = $unified['prepay_id'];
        $values['mweb_url'] = $unified['mweb_url'];
        return $values;
    }


    /**
     * 调起APP支付
     * @param array $data
     * @param null $config
     * @return array|bool|mixed|string
     */
    public function getAppPay(array $data, array $config)
    {
        $unified = $this->UnifiedOrder('APP', $data, $config);//统一下单
        if (is_string($unified)) {
            $this->debug(['APP.统一下单异常：', $data, $config])->error($unified);
            return $unified;
        }

        $rest = [];
        $rest['appid'] = $config['appid'];
        $rest['partnerid'] = $config['mchid'];//商户号
        $rest['prepayid'] = $unified['prepay_id'];//微信返回的支付交易会话ID
        $rest['package'] = 'Sign=WXPay';
        $rest['noncestr'] = str_rand(30);//随机数
        $rest['timestamp'] = $data['time'];
        $rest['sign'] = $this->createSign($rest, $config['token']);
        return $rest;
    }


    /**
     * 统一下单
     * @param array $data
     * @param string $type
     * @param $config
     * @return bool|mixed|string
     * https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_1
     * https://pay.weixin.qq.com/wiki/doc/api/app/app.php?chapter=9_1
     *
     * 绑定域名
     * https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=7_3
     */
    private function UnifiedOrder(string $type, array $data, array $config = [])
    {
        foreach (['appid', 'mchid', 'token', 'cert.cert', 'cert.key'] as $ck) {
            if (!isset($config[$ck])) return "缺少{$ck}项";
        }
        $type = strtoupper($type);
        $this->debug([$type, $config]);

        $data += [
            'id' => mt_rand(),//订单号
            'subject' => '充值订单',//商品简要说明
            'ip' => _CIP,
            'nonce_str' => str_rand(15),//随机签名
            'fee' => 1,//金额
            'openid' => '',//OpenID
            'attach' => str_rand(),////选填 附加数据，在查询API和支付通知中原样返回，该字段主要用于商户携带订单的自定义数据
            'notify' => ''
        ];

        if (empty($data['id'])) return "订单号不能为空";
        if (empty($data['notify'])) return "异步回调通知URL不能为空";
        if (empty($data['fee']) or $data['fee'] < 1) return "支付金额不能为空";

        $payInfo = array();
        $payInfo['appid'] = $config['appid'];        //微信分配的公众账号ID
        $payInfo['mch_id'] = $config['mchid'];    //微信支付分配的商户号
        $payInfo['notify_url'] = $data['notify'];    //接收微信支付异步通知回调地址
        $payInfo['trade_type'] = $type;                    //交易类型 取值如下：JSAPI，NATIVE，APP，WAP
        $payInfo['spbill_create_ip'] = $data['ip'];    //客户端IP APP和网页支付提交用户端ip，Native支付填调用微信支付API的机器IP

        $payInfo['nonce_str'] = $data['nonce_str'];    //签名 随机字符串，不长于32位。推荐随机数生成算法
        $payInfo['body'] = $data['subject'];        //商品简要说明

        $payInfo['out_trade_no'] = $data['id'];    //商户系统内部的订单号,32个字符内、可包含字母,
        $payInfo['total_fee'] = $data['fee'];        //订单总金额，只能为整数，
        $payInfo['device_info'] = 'WEB';                    //终端设备号(门店号或收银设备ID)，注意：PC网页或公众号内支付请传"WEB"
        $payInfo['product_id'] = $data['id'];            //trade_type=NATIVE时此参数必传。此id为二维码中包含的商品ID，商户自行定义。

        if (isset($config['credit']) and !$config['credit']) $payInfo['limit_pay'] = 'no_credit';            //不能使用信用卡

        //trade_type=JSAPI时此参数必传，用户在商户appid下的唯一标识。
        if ($type === 'JSAPI') $payInfo['openid'] = $data['openid'];
        $payInfo['time_expire'] = date('YmdHis', time() + 7200);//有效期

        $payInfo['attach'] = $data['attach'];        //选填 附加数据，在查询API和支付通知中原样返回，该字段主要用于商户携带订单的自定义数据
        $payInfo['sign'] = $this->createSign($payInfo, $config['token']);//签名，详见签名生成算法
        $payInfo = $this->xml($payInfo);
        $this->debug([$data, $config, $payInfo]);

        $api = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
        $option = [];
        $option['encode'] = 'xml';
        $option['type'] = 'post';
        $option['cert'] = ['cert' => $config['cert.cert'], 'key' => $config['cert.key']];

        $content = $this->Request($api, $payInfo, $option);
        $err = "统一下单({$type}){$config['appid']}:";

        if (!is_array($content)) return "{$err}{$content}";
        if ($content['return_code'] !== 'SUCCESS') return "{$err}{$content['return_msg']}";        //生成支付时错误
        if ($content['result_code'] !== 'SUCCESS') return "{$err}{$content['err_code_des']}";        //生成支付时错误
        if (!$this->checkSign($content, $config['token'])) return "{$err}返回签名错误";
        return $content;
    }

    /**
     * 查询订单
     * @param array $way
     * @param string $ordNumber
     * @return string|array
     */
    public function orderQuery(array $way, string $ordNumber, string $ordTansID = null)
    {
        $api = 'https://api.mch.weixin.qq.com/pay/orderquery';
        $data = [];
        $data['appid'] = $way['appid'];
        $data['mch_id'] = $way['mchid'];
        if ($ordTansID) {
            $data['transaction_id'] = $ordTansID;
        } else {
            $data['out_trade_no'] = $ordNumber;
        }
        $data['nonce_str'] = str_rand(20);
//        $data['sign_type'] = 'MD5';
        $data['sign'] = $this->createSign($data, $way['token']);//签名，详见签名生成算法
        $data = $this->xml($data);

        $option = [];
        $option['encode'] = 'xml';
        $option['type'] = 'post';

        $content = $this->Request($api, $data, $option);
        if (!is_array($content)) return 'Q:订单查询-' . $content;
        if ($content['return_code'] !== 'SUCCESS') return 'Q:订单查询-' . $content['return_msg'];        //生成支付时错误
        if ($content['result_code'] !== 'SUCCESS') return 'Q:订单查询-' . $content['err_code_des'];        //生成支付时错误
        if (!$this->checkSign($content, $way['token'])) return 'Q:订单查询-返回签名错误';

        $this->debug($content);

        $rest = [];
        $rest['success'] = ($content['trade_state'] === 'SUCCESS');
        $rest['ordNumber'] = $content['out_trade_no'];
        $rest['desc'] = $content['trade_state_desc'];
        if ($rest['success']) {
            $rest['wayTradeNo'] = $content['transaction_id'];
            $rest['payTime'] = strtotime($content['time_end']);
        } else {
            $rest['wayTradeNo'] = '';
            $rest['payTime'] = 0;
        }

        return $rest;
    }

    /**
     * 检查签名
     * @param $data
     * @param null $key
     * @return bool
     */
    public function checkSign($data, $key)
    {
        $checkSign = $this->createSign($data, $key);
        return hash_equals($checkSign, $data['sign']);
    }

    //生成签名
    public function createSign($arrValue, $key)
    {
        ksort($arrValue);
        $string = $this->ToUrlParams($arrValue);
        return strtoupper(md5("{$string}&key={$key}"));
    }

    private function ToUrlParams($arrValue)
    {
        $buff = [];
        foreach ($arrValue as $k => &$v) {
            if (!in_array($k, ['sign', 'paySign']) and $v != '' && !is_array($v)) {
                $buff[] = "{$k}={$v}";
            }
        }
        return implode('&', $buff);
    }


}