<?php

namespace esp\weiXin\app;

use esp\error\Error;
use Exception;
use function esp\helper\mk_dir;

class App extends _Base
{


    /**
     * 读取风险等级，调用此接口必须在用户访问小程序的2小时内进行
     *
     * https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/safety-control-capability/riskControl.getUserRiskRank.html
     *
     * @param array $param
     * @return array
     */
    public function readRiskRank(array $param): array
    {
        $api = "/wxa/getuserriskrank?access_token={access_token}";
        $scene = $param['scene'] ?? [0, 1];
        if (is_int($scene)) $scene = [$scene];

        $post = [];
        $post['appid'] = $this->AppID;
        $post['openid'] = $param['openid'];
        $post['client_ip'] = $param['ip'];
        if (isset($param['mobile'])) $post['mobile_no'] = $param['mobile'];
        if (isset($param['email'])) $post['email_address'] = $param['email'];
        if (isset($param['extended'])) $post['extended_info'] = $param['extended'];
        $post['is_test'] = false;

        $option = [];
        $option['type'] = 'post';
        $option['encode'] = 'json';

        $value = [];
        foreach ($scene as $sc) {
            $post['scene'] = $sc;
            $risk = $this->Request($api, $post, $option);
            if (is_string($risk)) {
                $value[$sc] = $risk;
            } else {
                $value[$sc] = $risk['risk_rank'];
            }
        }

        return $value;
    }


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
     * @param array $params
     * @return array|bool|string
     * @throws Error
     */
    public function getUnlimited(string $fileName, string $scene, array $params = [])
    {
        $option = [];
        $option['encode'] = 'json';
        $option['decode'] = 'buffer';
        $option['buffer'] = $fileName;

        $data = [];
        $data['page'] = 'init';//不能携带参数（参数请放在scene字段里）
        $data['width'] = 240;//280-1280
        $data['scene'] = $scene;
//        $data['is_hyaline'] = true;//默认是false，是否需要透明底色，为 true 时，生成透明底色的小程序

        $api = "/wxa/getwxacodeunlimit?access_token={access_token}";
        $rest = $this->Request($api, $params + $data, $option);
        if (is_string($rest)) return $rest;
        if ($msg = $rest->error()) return $msg;

        $html = $rest->html();
        if ($html[0] === '{') {
            $json = json_decode($html, true);
            return $json['errmsg'] ?? $html;
        }

        return true;
    }

    /**
     * 获取【小程序码】，适用于需要的码数量较少的业务场景。通过该接口生成的小程序码，永久有效，有数量限制，详见获取二维码。
     * https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/qr-code/wxacode.get.html
     * path可以带参数，但是 与 wxacode.createQRCode 总共生成的码数量限制为 10万条
     * 适用一些广告场景等地方，可以带参数，参数基本没限制，在小程序中可以进行识别
     * 但是path总长不能超过 128 字
     *
     * @param string $fileName
     * @param array $param
     * @param int $width
     * @return array|bool|string
     */
    public function getWxACode(string $fileName, array $param, int $width = 430)
    {
        $option = [];
        $option['encode'] = 'json';
        $option['decode'] = 'buffer';
        $option['buffer'] = $fileName;

        $data = [];
        $data['page'] = 'init';
//        $data['page'] = '/init?' . http_build_query($param);
//        $data['page'] = json_encode($param, 256 | 64);
        $data['width'] = $width;//280-1280

        $api = "/wxa/getwxacode?access_token={access_token}";
        $rest = $this->Request($api, $param + $data, $option);
        if (is_string($rest)) return $rest;
        if ($msg = $rest->error()) return $msg;

        $html = $rest->html();
        if ($html[0] === '{') {
            $json = json_decode($html, true);
            return $json['errmsg'] ?? $html;
        }
        return true;
    }

    /**
     * 获取小程序【二维码】，适用于需要的码数量较少的业务场景。通过该接口生成的小程序码，永久有效，有数量限制，详见获取二维码。
     * https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/qr-code/wxacode.createQRCode.html
     *
     *
     * @param string $fileName
     * @param array $param
     * @param int $width
     * @return array|bool|string
     */
    public function createQRCode(string $fileName, array $param, int $width = 430)
    {
        $option = [];
        $option['encode'] = 'json';
        $option['decode'] = 'buffer';
        $option['buffer'] = $fileName;

        $data = [];
        $data['page'] = 'init';
//        $data['page'] = '/init?' . http_build_query($param);
//        $data['page'] = json_encode($param, 256 | 64);
        $data['width'] = $width;//280-1280

        $api = "/cgi-bin/wxaapp/createwxaqrcode?access_token={access_token}";
        $rest = $this->Request($api, $param + $data, $option);
        if (is_string($rest)) return $rest;
        if ($msg = $rest->error()) return $msg;
        $html = $rest->html();
        if ($html[0] === '{') {
            $json = json_decode($html, true);
            return $json['errmsg'] ?? $html;
        }
        return true;
    }


    /**
     * 订阅物流消息
     * @param $openid
     * @param $receiver_phone
     * @param $waybill_id
     * @param $trans_id
     * @param $goods_name
     * @param $goods_pic_url
     * @return bool|mixed|string
     * @throws Exception
     */
    public function followWaybill($openid, $receiver_phone, $waybill_id, $trans_id, $goods_name, $goods_pic_url)
    {
        $option = [];
        $option['encode'] = 'html';
//        $option['debug'] = false;

        $data = [];
        $data['openid'] = $openid;//不能携带参数（参数请放在scene字段里）
        $data['receiver_phone'] = $receiver_phone;//280-1280
        $data['waybill_id'] = $waybill_id;
        $data['goods_info'] = [
            'detail_list' => [
                'goods_name' => $goods_name,
                'goods_img_url' => $goods_pic_url
            ]
        ];
        $data['trans_id'] = $trans_id;
//        $data['is_hyaline'] = true;
        $data = json_encode($data, 256 | 64);

        $api = "/cgi-bin/express/delivery/open_msg/follow_waybill?access_token={access_token}";
        return $this->Request($api, $data, $option);
    }

    /**
     * 更新用户积分
     * @param array $param
     * @return array
     * @throws Exception
     */
    public function updateUser(array $param)
    {
        $post = [];
        $post['code'] = $param['code'];
        $post['card_id'] = $param['card_id'];
        $post['add_bonus'] = $param['add_bonus'];

        $option = [];
        $option['type'] = 'post';
        $option['encode'] = 'json';

        $api = "/card/membercard/updateuser?access_token={access_token}";
        return $this->Request($api, $post, $option);
    }


}