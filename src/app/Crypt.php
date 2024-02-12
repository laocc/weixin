<?php

namespace esp\weiXin\app;


final class Crypt
{
    private string $appid;
    private string $sessionKey;

    /**
     * 解密小程序中用户授权时的信息等
     *
     * 构造函数
     * @param $appid string 小程序的appid
     * @param $sessionKey string 用户在小程序登录后获取的会话密钥
     */
    public function __construct(string $appid, string $sessionKey)
    {
        $this->appid = $appid;
        $this->sessionKey = $sessionKey;
    }

    /**
     * 检验数据的真实性，并且获取解密后的明文.
     * @param string $encryptedData string 加密的用户数据
     * @param string $iv string 与用户数据一同返回的初始向量
     * @param $data
     *
     * @return bool|string 成功0，失败返回对应的错误码
     */
    public function decryptData(string $encryptedData, string $iv, &$data)
    {
        if (empty($this->sessionKey)) return 'sessionKey 不能为空';
        if (strlen($this->sessionKey) !== 24) return 'sessionKey 非法，须为24位长';
        if (strlen($iv) !== 24) return 'iv 非法，须为24位长';
        $aesKey = base64_decode($this->sessionKey);
        $aesIV = base64_decode($iv);
        $aesCipher = base64_decode($encryptedData);

        $result = openssl_decrypt($aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);
        if ($result === false) return '解析失败';

        $data = json_decode($result, true);
        if ($data === NULL) return '解密buffer非法';
        $appid = $data['watermark']['appid'] ?? '';
        if ($appid && ($appid != $this->appid)) {
            return "解析appid不符：{$appid}!=={$this->appid}";
        }

        return true;
    }

}

