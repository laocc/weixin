<?php

namespace esp\weixin\auth;


final class Crypt
{
    private $token;
    private $appId;
    private $dataType;
    private $encodingAesKey;

    /**
     * WXBizMsgCrypt constructor.
     * Crypt constructor.
     * @param string $appId 公众平台的appId
     * @param string $token 公众平台上，开发者设置的token
     * @param string $encodingAesKey 公众平台上，开发者设置的EncodingAESKey
     * @param string $type 消息格式类型，json或xml
     */
    public function __construct(string $appId, string $token, string $encodingAesKey, string $type = 'xml')
    {
        $this->appId = $appId;
        $this->token = $token;
        $this->encodingAesKey = $encodingAesKey;
        $this->dataType = $type;
    }

    /**
     * @param string $text
     * @param string|null $timeStamp
     * @param string|null $nonce
     * @return int
     */
    public function encode(string $text, string $timeStamp = null, string $nonce = null)
    {
        $errCode = $this->encryptMsg($text, $timeStamp, $nonce, $encryptMsg);
        if ($errCode !== 0) return $errCode;

        return $encryptMsg;
    }

    /**
     * 第三方收到公众号平台发送的消息 解密
     * @param string $encryptMsg
     * @param string $sign
     * @param string $timeStamp
     * @param string $nonce
     * @return array
     */
    public function decode(string $encryptMsg, string $sign, string $timeStamp, string $nonce): array
    {
        if ($this->dataType === 'json') {
            $data = json_decode($encryptMsg, true);
            $encrypt = $data['Encrypt'];
        } else {
            $xml = new \DOMDocument();
            $xml->loadXML($encryptMsg);
            $Encrypt = $xml->getElementsByTagName('Encrypt');
            $encrypt = $Encrypt->item(0)->nodeValue;

            if (is_null($sign)) {
                $Encrypt = $xml->getElementsByTagName('MsgSignature');
                $sign = $Encrypt->item(0)->nodeValue;
            }
        }

        $format = "<xml><Encrypt><![CDATA[%s]]></Encrypt></xml>";
        $from_xml = sprintf($format, $encrypt);

        $errCode = $this->decryptMsg($sign, $timeStamp, $nonce, $from_xml, $msg);
        if ($errCode !== 0) return ['error' => $errCode];

        return $msg;
    }

    /**
     * 将公众平台回复用户的消息加密打包.
     * <ol>
     *    <li>对要发送的消息进行AES-CBC加密</li>
     *    <li>生成安全签名</li>
     *    <li>将消息密文和安全签名打包成xml格式</li>
     * </ol>
     *
     * @param $replyMsg string 公众平台待回复用户的消息，xml格式的字符串
     * @param $timeStamp string 时间戳，可以自己生成，也可以用URL参数的timestamp
     * @param $nonce string 随机串，可以自己生成，也可以用URL参数的nonce
     * @param &$encryptMsg string 加密后的可以直接回复用户的密文，包括msg_signature, timestamp, nonce, encrypt的xml格式的字符串,
     *                      当return返回0时有效
     *
     * @return int 成功0，失败返回对应的错误码
     */
    private function encryptMsg(string $replyMsg, $timeStamp, $nonce, &$encryptMsg)
    {
        $pc = new PrpCrypt($this->encodingAesKey);
        if (is_null($timeStamp)) $timeStamp = time();
        if (is_null($nonce)) $nonce = mt_rand();

        //加密
        $arrayEncrypt = $pc->encrypt($replyMsg, $this->appId);
        if ($arrayEncrypt[0] !== 0) return $arrayEncrypt[0];

        //生成安全签名
        $arraySign = $this->getSHA1($this->token, $timeStamp, $nonce, $arrayEncrypt[1]);
        if ($arraySign[0] !== 0) return $arraySign[0];

        if ($this->dataType === 'json') {
            $data = [];
            $data['Encrypt'] = $arrayEncrypt[1];
            $data['MsgSignature'] = $arraySign[1];
            $data['TimeStamp'] = $timeStamp;
            $data['Nonce'] = $nonce;
            $encryptMsg = json_encode($data, 256 | 64);
            return ErrorCode::$OK;
        }

        $xml = <<<XML
<xml>
<Encrypt><![CDATA[%s]]></Encrypt>
<MsgSignature><![CDATA[%s]]></MsgSignature>
<TimeStamp>%s</TimeStamp>
<Nonce><![CDATA[%s]]></Nonce>
</xml>
XML;
        $encryptMsg = sprintf($xml, $arrayEncrypt[1], $arraySign[1], $timeStamp, $nonce);
        return ErrorCode::$OK;
    }


    /**
     * 检验消息的真实性，并且获取解密后的明文.
     * <ol>
     *    <li>利用收到的密文生成安全签名，进行签名验证</li>
     *    <li>若验证通过，则提取xml中的加密消息</li>
     *    <li>对消息进行解密</li>
     * </ol>
     *
     * @param $msgSignature string 签名串，对应URL参数的msg_signature
     * @param $timestamp string 时间戳 对应URL参数的timestamp
     * @param $nonce string 随机串，对应URL参数的nonce
     * @param $postData string 密文，对应POST请求的数据
     * @param &$msg string 解密后的原文，当return返回0时有效
     *
     * @return int 成功0，失败返回对应的错误码
     */
    private function decryptMsg(string $msgSignature, string $timestamp, string $nonce, string $postData, &$msg)
    {
        if (strlen($this->encodingAesKey) != 43) {
            return ErrorCode::$IllegalAesKey;
        }

        $pc = new PrpCrypt($this->encodingAesKey);

        //提取密文
        $arrayXML = $this->extract($postData);
        if ($arrayXML[0] !== 0) return $arrayXML[0];

        //验证签名
        $arraySign = $this->getSHA1($this->token, $timestamp, $nonce, $arrayXML[1]);
        if ($arraySign[0] !== 0) return $arraySign[0];

        if ($arraySign[1] !== $msgSignature) {
            return ErrorCode::$ValidateSignatureError;
        }

        $result = $pc->decrypt($arrayXML[1], $this->appId);
        if ($result[0] !== 0) return $result[0];

        if ($this->dataType === 'json') {
            $msg = json_decode($result[1], true);

        } else {
            $msg = \esp\helper\xml_decode($result[1], true);
        }

        return ErrorCode::$OK;
    }

    /**
     * 用SHA1算法生成安全签名
     * @param string $token 票据
     * @param string $timestamp 时间戳
     * @param string $nonce 随机字符串
     * @param string $encrypt_msg 密文消息
     * @return array
     */
    private function getSHA1(string $token, string $timestamp, string $nonce, string $encrypt_msg)
    {
        try {
            $array = array($encrypt_msg, $token, $timestamp, $nonce);
            sort($array, SORT_STRING);
            $str = implode($array);
            return array(ErrorCode::$OK, sha1($str));
        } catch (\Exception $e) {
            return array(ErrorCode::$ComputeSignatureError, null);
        }
    }


    /**
     * 提取出xml数据包中的加密消息
     * @param string $xmlText 待提取的xml字符串
     * @return array 提取出的加密消息字符串
     */
    private function extract(string $xmlText)
    {
        try {
            $xml = new \DOMDocument();
            $xml->loadXML($xmlText);
            $array_e = $xml->getElementsByTagName('Encrypt');
            $encrypt = $array_e->item(0)->nodeValue;
            return array(0, $encrypt);
        } catch (\Exception $e) {
            return array(ErrorCode::$ParseXmlError, null);
        }
    }

}
