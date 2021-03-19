<?php

namespace esp\weiXin\auth;

/**
 * PrpCrypt class
 *
 * 提供接收和推送给公众平台消息的加解密接口.
 */
final class PrpCrypt
{
    public $key;

    function __construct($k)
    {
        $this->key = base64_decode($k . "=");
    }

    /**
     * 对明文进行加密
     * @param string $text 需要加密的明文
     * @param string $appID 加密后的密文
     * @return array
     */
    public function encrypt(string $text, string $appID)
    {
        try {
            $random = $this->getRandomStr();//获得16位随机字符串，填充到明文之前
            $text = $random . pack("N", strlen($text)) . $text . $appID;
            $iv = substr($this->key, 0, 16);
            $pkc_encoder = new PKCS7Encoder;
            $text = $pkc_encoder->encode($text);
            $encrypted = openssl_encrypt($text, 'AES-256-CBC', substr($this->key, 0, 32), OPENSSL_ZERO_PADDING, $iv);
            return array(ErrorCode::$OK, $encrypted);
        } catch (\Exception $e) {
            return array(ErrorCode::$EncryptAESError, null);
        }
    }

    /**
     * 对密文进行解密
     * @param string $encrypted 需要解密的密文
     * @param string $appID 解密得到的明文
     * @return array|string
     */
    public function decrypt(string $encrypted, string $appID)
    {
        try {
            $decrypted = openssl_decrypt($encrypted,
                'AES-256-CBC',
                substr($this->key, 0, 32),
                OPENSSL_ZERO_PADDING,
                substr($this->key, 0, 16));

        } catch (\Exception $e) {
            return array(ErrorCode::$DecryptAESError, null);
        }

        try {
            //去除补位字符
            $pkc_encoder = new PKCS7Encoder;
            $result = $pkc_encoder->decode($decrypted);
            //去除16位随机字符串,网络字节序和AppId
            if (strlen($result) < 16) return [ErrorCode::$DecryptAESError, null];

            $content = substr($result, 16, strlen($result));
            $len_list = unpack("N", substr($content, 0, 4));
            $xml_len = $len_list[1];
            $xml_content = substr($content, 4, $xml_len);
            $from_appID = substr($content, $xml_len + 4);
            if (!$appID) $appID = $from_appID;
            //如果传入的appid是空的，则认为是订阅号，使用数据中提取出来的appid
        } catch (\Exception $e) {
            return array(ErrorCode::$IllegalBuffer, null);
        }
        if (strpos($from_appID, $appID) !== 0) return array(ErrorCode::$ValidateAppIdError, "{$from_appID}/{$appID}");

        return array(0, $xml_content, $from_appID);
    }


    /**
     * 随机生成16位字符串
     * @param int $len
     * @return string
     */
    private function getRandomStr(int $len = 16)
    {
        $str = "";
        $str_pol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen($str_pol) - 1;
        for ($i = 0; $i < $len; $i++) $str .= $str_pol[mt_rand(0, $max)];
        return $str;
    }

}
