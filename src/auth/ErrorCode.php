<?php

namespace esp\weiXin\auth;


/**
 * Class ErrorCode
 * @package esp\weixin\auth
 */
final class ErrorCode
{
    public static $OK = 0;
    public static $ValidateSignatureError = -40001;//签名验证错误
    public static $ParseXmlError = -40002;//xml解析失败
    public static $ComputeSignatureError = -40003;//sha加密生成签名失败
    public static $IllegalAesKey = -40004;//encodingAesKey 非法
    public static $ValidateAppIdError = -40005;//appid 校验错误
    public static $EncryptAESError = -40006;//aes 加密失败
    public static $DecryptAESError = -40007;//aes 解密失败
    public static $IllegalBuffer = -40008;//解密后得到的buffer非法
    public static $EncodeBase64Error = -40009;//base64加密失败
    public static $DecodeBase64Error = -40010;//base64解密失败
    public static $GenReturnXmlError = -40011;//生成xml失败
}


