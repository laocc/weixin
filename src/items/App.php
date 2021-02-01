<?php

namespace esp\weixin\items;

use function esp\helper\mk_dir;
use esp\weixin\Base;

class App extends Base
{
    /**
     * 获取【小程序码】
     * 获取小程序码，适用于需要的码数量极多的业务场景。通过该接口生成的小程序码，永久有效，数量暂无限制。 更多用法详见 获取二维码。
     * https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/qr-code/wxacode.getUnlimited.html
     * 调用分钟频率受限(目前5000次/分钟，会调整)，如需大量小程序码，建议预生成。
     * 适用在固定只进入首页的场景，如用户分享，
     * 因为不能带参数，参数只能放在scene里，而且scene值最长32字，且有些字符不能用，比如json里的双引号
     *
     * @param string $fileName
     * @param string $scene
     * @param int $width
     * @return array|bool|string
     */
    public function getUnlimited(string $fileName, string $scene, int $width = 430)
    {
        $option = [];
        $option['encode'] = 'html';
//        $option['debug'] = false;

        $data = [];
        $data['page'] = 'init';//不能携带参数（参数请放在scene字段里）
        $data['width'] = $width;//280-1280
        $data['scene'] = $scene;
//        $data['is_hyaline'] = true;

        $api = "/wxa/getwxacodeunlimit?access_token={access_token}";
        $rest = $this->Request($api, $data, $option);
        if (is_string($rest)) return $rest;
        if ($msg = $rest->error()) return $msg;
        $html = $rest->html();
        if ($html[0] === '{') {
            $json = json_decode($html, true);
            return $json['errmsg'] ?? $html;
        }

        mk_dir($fileName);
        $file = fopen($fileName, "w");
        fwrite($file, $html);
        fclose($file);
        return true;
    }

    /**
     * 获取【小程序码】，适用于需要的码数量较少的业务场景。通过该接口生成的小程序码，永久有效，有数量限制，详见获取二维码。
     * https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/qr-code/wxacode.get.html
     * path可以带参数，但是 与 wxacode.createQRCode 总共生成的码数量限制为 10万条
     * 适用一些广告场景等地方，可以带参数，参数基本没限制，在小程序中可以进行识别
     * 但是path总长不能超过128字
     *
     * @param string $fileName
     * @param array $param
     * @param int $width
     * @return array|bool|string
     */
    public function getWxACode(string $fileName, array $param, int $width = 430)
    {
        $option = [];
        $option['encode'] = 'html';
//        $option['debug'] = false;

        $data = [];
        $data['page'] = 'init';
//        $data['page'] = '/init?' . http_build_query($param);
//        $data['page'] = json_encode($param, 256 | 64);
        $data['width'] = $width;//280-1280

        $api = "/wxa/getwxacode?access_token={access_token}";
        $rest = $this->Request($api, $data, $option);
        if (is_string($rest)) return $rest;
        if ($msg = $rest->error()) return $msg;
        $html = $rest->html();
        if ($html[0] === '{') {
            $json = json_decode($html, true);
            return $json['errmsg'] ?? $html;
        }
        mk_dir($fileName);
        $file = fopen($fileName, "w");
        fwrite($file, $html);
        fclose($file);
        return true;
    }

    /**
     * 获取小程序【二维码】，适用于需要的码数量较少的业务场景。通过该接口生成的小程序码，永久有效，有数量限制，详见获取二维码。
     * https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/qr-code/wxacode.createQRCode.html
     */
    /**
     * @param string $fileName
     * @param array $param
     * @param int $width
     * @return array|bool|string
     */
    public function createQRCode(string $fileName, array $param, int $width = 430)
    {
        $option = [];
        $option['encode'] = 'html';
//        $option['debug'] = false;

        $data = [];
        $data['page'] = 'init';
//        $data['page'] = '/init?' . http_build_query($param);
//        $data['page'] = json_encode($param, 256 | 64);
        $data['width'] = $width;//280-1280

        $api = "/cgi-bin/wxaapp/createwxaqrcode?access_token={access_token}";
        $rest = $this->Request($api, $data, $option);
        if (is_string($rest)) return $rest;
        if ($msg = $rest->error()) return $msg;
        $html = $rest->html();
        if ($html[0] === '{') {
            $json = json_decode($html, true);
            return $json['errmsg'] ?? $html;
        }

        mk_dir($fileName);

        $file = fopen($fileName, "w");
        fwrite($file, $html);
        fclose($file);
        return true;
    }


}