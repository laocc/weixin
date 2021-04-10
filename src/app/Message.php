<?php

namespace esp\weiXin\app;


use esp\library\ext\Xml;
use esp\weiXin\Base;

final class Message extends Base
{

    /**
     * 小程序的客服消息
     * @param array $data
     * @return string
     * @throws \esp\error\EspError
     */
    public function acceptMessage(array $data)
    {
        $appRealID = $data['ToUserName'];
        $openID = $data['FromUserName'];

        switch ($data['MsgType']) {
            case 'text'://收到客户消息
                $text = $data['Content'] ?? '';
                $MsgId = $data['MsgId'] ?? '';
                $return = [];
                $return['ToUserName'] = $openID;
                $return['FromUserName'] = $appRealID;
                $return['CreateTime'] = time();
                $return['MsgType'] = 'transfer_customer_service';
                return (new Xml($return, 'xml'))->render(false);
                break;
            case 'event':
                switch ($data['Event']) {
                    case 'kf_create_session'://客服受理上
                        $kf = $data['KfAccount'];//客服的账号

                        break;
                    case 'kf_close_session'://关闭会话
                        $kf = $data['KfAccount'];//客服的账号
                        $type = $data['CloseType'];//谁关闭的

                        break;
                    case 'user_enter_tempsession'://用户接入成功
                        $plat = $data['SessionFrom'];
                        break;
                }
                break;

        }

        return 'success';
    }


}