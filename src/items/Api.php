<?php

namespace esp\weixin\items;


use esp\weixin\Base;

final class Api extends Base
{

    /**
     * 获取jsapi_ticket
     * @return bool|mixed|string
     */
    final private function load_ApiTicket()
    {
        $ticket = $this->tempCache('ApiTicket');
        if ($ticket and $ticket['expires'] > time()) return $ticket;

        $api = "/cgi-bin/ticket/getticket?type=jsapi&access_token={access_token}";
        $dat = $this->Request($api);
        if (is_string($dat)) return $dat;

        $dat = ['ticket' => $dat['ticket'], 'expires' => intval($dat['expires_in']) + time() - 100];

        $this->tempCache('ApiTicket', $dat);
        return $dat;
    }


    /**
     *  生成微信JS-SDK的config
     *
     * @param string $jsApiList
     * @param bool|false $debug
     * @return string|array
     *
     * https://mp.weixin.qq.com/wiki/7/aaa137b55fb2e0456bf8dd9148dd613f.html
     */
    public function ApiTicketJS(string $url, int $time = 0, bool $debug = false)
    {
        $aJS = array();

        $aJS[] = 'updateAppMessageShareData';//自定义“分享给朋友”及“分享到QQ”按钮的分享内容
        $aJS[] = 'updateTimelineShareData';//自定义“分享到朋友圈”及“分享到QQ空间”按钮的分享内容

        $aJS[] = 'onMenuShareTimeline';//分享到朋友圈
        $aJS[] = 'onMenuShareAppMessage';//分享朋友或群

        $aJS[] = 'onMenuShareWeibo';//分享到微博

//            $aJS[] = 'onMenuShareQZone';//分享到QQ空间
//
//            $aJS[] = 'startRecord';//开始录音接口
//            $aJS[] = 'stopRecord';//停止录音接口
//            $aJS[] = 'onVoiceRecordEnd';//监听录音自动停止接口
//            $aJS[] = 'playVoice';//播放语音接口
//            $aJS[] = 'pauseVoice';//暂停播放接口
//            $aJS[] = 'stopVoice';//停止播放接口
//            $aJS[] = 'onVoicePlayEnd';//监听语音播放完毕接口
//            $aJS[] = 'uploadVoice';//上传语音接口
//            $aJS[] = 'downloadVoice'; //下载语音接口
//            $aJS[] = 'translateVoice'; //识别音频并返回识别结果接口
//
//            $aJS[] = 'chooseImage';//拍照或从手机相册中选图接口
//            $aJS[] = 'previewImage';//预览图片接口
//            $aJS[] = 'uploadImage';//上传图片接口
//            $aJS[] = 'downloadImage'; //下载图片接口
//
//        $aJS[] = 'getNetworkType'; //获取网络状态接口
//        $aJS[] = 'openLocation'; //使用微信内置地图查看位置接口
//        $aJS[] = 'getLocation'; //获取地理位置接口
//
//        $aJS[] = 'hideOptionMenu'; //隐藏右上角菜单接口
//        $aJS[] = 'showOptionMenu'; //显示右上角菜单接口
//        $aJS[] = 'hideMenuItems'; //批量隐藏功能按钮接口
//        $aJS[] = 'showMenuItems'; //批量显示功能按钮接口
//        $aJS[] = 'hideAllNonBaseMenuItem'; //隐藏所有非基础按钮接口
//        $aJS[] = 'showAllNonBaseMenuItem'; //显示所有功能按钮接口
//        $aJS[] = 'closeWindow'; //关闭当前网页窗口接口
//        $aJS[] = 'chooseWXPay'; //发起一个微信支付请求
//        $aJS[] = 'openProductSpecificView'; //跳转微信商品页接口
//        $aJS[] = 'addCard'; //批量添加卡券接口
//        $aJS[] = 'chooseCard'; //拉取适用卡券列表并获取用户选择信息
//        $aJS[] = 'openCard'; //查看微信卡包中的卡券接口
        $aJS[] = 'scanQRCode'; //调起微信扫一扫接口
        $aJS[] = 'checkJsApi'; //
        $aJS[] = 'error'; //出错？

        $ticket = $this->load_ApiTicket();
        if (is_string($ticket)) return $ticket;

        $arrValue = array();
        $arrValue['noncestr'] = \esp\helper\str_rand(30);
        $arrValue['timestamp'] = $time ?: time();
        $arrValue['jsapi_ticket'] = $ticket['ticket'];
        $arrValue['url'] = $url;
        ksort($arrValue);
        $buff = [];
        foreach ($arrValue as $k => &$v) $buff[] = "{$k}={$v}";
        $rawString = implode('&', $buff);

        $arrApi = array();
        $arrApi['debug'] = $debug ? true : false;// 开启调试模式,
        $arrApi['appId'] = $this->AppID;// 必填，公众号的唯一标识
        $arrApi['timestamp'] = $arrValue['timestamp']; // 必填，生成签名的时间戳
        $arrApi['nonceStr'] = $arrValue['noncestr'];// 必填，生成签名的随机串
        $arrApi['signature'] = sha1($rawString);// 必填，签名
        $arrApi['jsApiList'] = $aJS;// 必填

        if (1) return $arrApi;

        $apiJson = json_encode($arrApi, 256 | 128 | 64);

        $js = "<script src='https://res.wx.qq.com/open/js/jweixin-1.4.0.js'>\n//\n";
//        $js = "<script src='https://res2.wx.qq.com/open/js/jweixin-1.4.0.js'>\n//\n";
        $js .= "</script>\n<script>\nwx.config({$apiJson});\n</script>\n";

        return $js;
    }
}