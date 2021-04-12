<?php

namespace esp\weiXin\items;

use esp\weiXin\Base;
use esp\weiXin\Send;

final class Push extends Base implements Send
{
    private $api = "/cgi-bin/message/mass/sendall?access_token={access_token}";

    public function setPreview()
    {
        $this->api = "/cgi-bin/message/mass/preview?access_token={access_token}";
        return $this;
    }


    public function send(array $content, array $option = [])
    {
        /**
         * https://developers.weixin.qq.com/doc/offiaccount/Message_Management/Batch_Sends_and_Originality_Checks.html#3
         */
        $data = [];
        $data['is_to_all'] = boolval($option['is_to_all'] ?? 1);
        $data['tag_id'] = $option['tag_id'] ?? 0;
        $data['reply'] = $option['reply'] ?? 0;
        $data['recommend'] = $option['recommend'] ?? '';//仅图片需要，推荐语，不填则默认为“分享图片”
        $data['send_ignore_reprint'] = 1;//章被判定为转载时，且原创文允许转载时，将继续进行群发操作。

        switch ($content['type']) {
            case 'text':
                $data['content'] = $content['text']['desc'];
                break;
            case 'voice':
                $data['media_id'] = $content['voice']['id'];
                break;
            case 'video':
                $data['media_id'] = $content['video']['id'];
                break;
            case 'image':
                $data['media_id'] = $content['image']['id'];
                break;
            case 'news':
                $data['media_id'] = $content['news']['id'];
                break;
        }

        $send = $this->{$content['type']}($data);
        if ($this->openID) {
            unset($send['filter'], $send['recommend'], $send['only_fans_can_comment'],
                $send['need_open_comment'], $send['send_ignore_reprint']);
            if (strlen($this->openID) > 26) {
                $send['touser'] = $this->openID;
            } else {
                $send['towxname'] = $this->openID;
            }
        }

        if (_CLI) print_r($send);
        $JsonStr = $this->Request($this->api, $send);
        if (is_string($JsonStr)) return $JsonStr;

        return $JsonStr;
    }

    private function news(array $option)
    {
        $data = [];
        $data['filter'] = [];
        $data['filter']['is_to_all'] = $option['is_to_all'];
        $data['filter']['tag_id'] = $option['tag_id'];
        $data['mpnews'] = [];
        $data['mpnews']['media_id'] = $option['media_id'];
        $data['msgtype'] = 'mpnews';
        $data['send_ignore_reprint'] = $option['send_ignore_reprint'];
        return $data;
    }

    private function text(array $option)
    {
        $data = [];
        $data['filter'] = [];
        $data['filter']['is_to_all'] = $option['is_to_all'];
        $data['filter']['tag_id'] = $option['tag_id'];
        $data['text'] = [];
        $data['text']['content'] = $option['content'];
        $data['msgtype'] = 'text';
        return $data;
    }

    private function voice(array $option)
    {
        $data = [];
        $data['filter'] = [];
        $data['filter']['is_to_all'] = $option['is_to_all'];
        $data['filter']['tag_id'] = $option['tag_id'];
        $data['voice'] = [];
        $data['voice']['media_id'] = $option['media_id'];
        $data['msgtype'] = 'voice';
        return $data;
    }

    private function video(array $option)
    {
        $data = [];
        $data['filter'] = [];
        $data['filter']['is_to_all'] = $option['is_to_all'];
        $data['filter']['tag_id'] = $option['tag_id'];
        $data['mpvideo'] = [];
        $data['mpvideo']['media_id'] = $option['media_id'];
        $data['msgtype'] = 'mpvideo';
        return $data;
    }

    private function image(array $option)
    {
        $data = [];
        $data['filter'] = [];
        $data['filter']['is_to_all'] = $option['is_to_all'];
        $data['filter']['tag_id'] = $option['tag_id'];
        $data['images'] = [];
        if ($this->openID) {
            $data['image']['media_id'] = $option['media_id'];
        } else {
            $data['images']['media_ids'] = [$option['media_id']];
        }

        $data['msgtype'] = 'image';
        $data['recommend'] = $option['recommend'] ?? '';//推荐语，不填则默认为“分享图片”
        $reply = $option['reply'] ?? 0;
        $data['need_open_comment'] = $reply ? 1 : 0;//Uint32 是否打开评论，0不打开，1打开
        $data['only_fans_can_comment'] = $reply === 1 ? 1 : 0;//Uint32 是否粉丝才可评论，0所有人可评论，1粉丝才可评论
        return $data;
    }

}