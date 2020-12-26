<?php

namespace esp\weixin\items;

use esp\core\Input;
use esp\weixin\Base;

final class Reply extends Base
{

    /**
     *
     * 对该消息进行响应（现支持回复文本、图片、图文、语音、视频、音乐）
     * 图文消息个数；当用户发送文本、图片、语音、视频、图文、地理位置这六种消息时，
     * 开发者只能回复1条图文消息；其余场景最多可回复8条图文消息
     *
     *
     * @param $reply
     * @return string
     * @throws \Exception
     */
    public function UnifiedReply($reply)
    {
        if (is_string($reply)) return $this->Text($reply);
        switch ($reply['type']) {
            case 'text':
                if (!empty($reply['link'])) {
                    $reply['text']['desc'] = "<a href='{$reply['link']}'>{$reply['text']['desc']}</a>";
                }
                return $this->Text($reply['text']['desc']);
                break;
            case 'image':
                return $this->Image($reply['image']['id']);
                break;
            case 'voice':
                return $this->Voice($reply['voice']['id']);
                break;
            case 'video':
                return $this->Video($reply);
                break;
            case 'article':
                return $this->Article($reply);
                break;
            case 'arts':
                return $this->ArticleS($reply);
                break;
            case 'pool':
                //实际这里不会出现pool
                break;
            case 'ask':
                break;
            case 'news':
                break;
            case 'template':
                break;
        }
        return 'success';
    }


    /**
     * 文本回复
     * @param string $text
     * @return string
     * @throws \Exception
     */
    public function Text(string $text)
    {
        if ($text === 'success') return $text;

        $reply = $this->_Reply_Template('text');
        $reply['Content'] = ($text);
        $xml = $this->xml($reply);
        return $xml;
    }

    /**
     * 图片消息
     * @param string $media_id
     * @return string
     * @throws \Exception
     */
    public function Image(string $media_id)
    {
        $reply = $this->_Reply_Template('image');
        $reply['Image'] = ['MediaId' => $media_id];
        $xml = $this->xml($reply);
        return $xml;
    }

    public function Music(array $data)
    {
        $reply = $this->_Reply_Template('music');
        $reply['Music'] = [];

        $reply['Music']['Title'] = $data['music']['title'];
        $reply['Music']['Description'] = $data['music']['desc'];
        $reply['Music']['MusicUrl'] = $data['music']['url'];
        $reply['Music']['HQMusicUrl'] = $data['music']['hq'];

        if (empty($reply['Music']['Title'])) unset($reply['Music']['Title']);
        if (empty($reply['Music']['Description'])) unset($reply['Music']['Description']);
        if (empty($reply['Music']['MusicUrl'])) unset($reply['Music']['MusicUrl']);
        if (empty($reply['Music']['HQMusicUrl'])) unset($reply['Music']['HQMusicUrl']);

        $reply['Music']['ThumbMediaId'] = $data['image']['id'];
        $xml = $this->xml($reply);
        return $xml;
    }

    public function Article(array $data)
    {
        $reply = $this->_Reply_Template('news');
        $reply['ArticleCount'] = 1;
        $reply['Articles'] = [];
        $reply['Articles']['item'] = [];
        $reply['Articles']['item']['Title'] = $data['text']['title'];
        $reply['Articles']['item']['Description'] = $data['text']['desc'];

        if (!empty($data['image']['url'])) {
            $reply['Articles']['item']['PicUrl'] = $data['image']['url'];
        } else {
            $reply['Articles']['item']['PicUrl'] = $this->resDomain . $data['image']['path'];
        }

        $reply['Articles']['item']['Url'] = $data['link'];
        $xml = $this->xml($reply);
        return $xml;
    }

    public function ArticleS(array $data)
    {
        $reply = $this->_Reply_Template('news');
        $reply['ArticleCount'] = count($data['arts']);
        $reply['Articles'] = [];
        $reply['Articles']['item'] = [];
        foreach ($data['arts'] as $ns) {
            $news = [];
            $news['Title'] = $ns['title'];
            $news['Description'] = $ns['desc'];
            $news['PicUrl'] = $ns['image'];
            $news['Url'] = $ns['url'];
            $reply['Articles']['item'][] = $news;
        }


        $xml = $this->xml($reply);
        return $xml;
    }

    public function Voice(string $media_id)
    {
        $reply = $this->_Reply_Template('voice');
        $reply['Voice'] = ['MediaId' => $media_id];
        $xml = $this->xml($reply);
        return $xml;
    }

    public function Video(array $option)
    {
        $reply = $this->_Reply_Template('video');
        $reply['Video'] = [
            'MediaId' => $option['video']['id'],
            'Title' => $option['text']['title'],
            'Description' => $option['text']['desc'],
        ];
        $xml = $this->xml($reply);
        return $xml;
    }


    /**
     * 接入验证，仅在微信官方中配置地址时才会调用到
     * 验证之后，不需要再调用此过程
     * @throws \\Exception
     * signature    =abb122b9ae77c1af86e7846f29bfee5534027c71&
     * echostr      =7777285193039077214&
     * timestamp    =1462410074&
     * nonce        =1031550820
     */
    public function verification()
    {
        if (!isset($_GET["echostr"])) return 'NULL';
        $echostr = Input::get('echostr');
        $timestamp = Input::get('timestamp');
        $signature = Input::get('signature');
        $nonce = Input::get('nonce');

        $tmpArr = [$this->mpp['mppToken'], $timestamp, $nonce];
        sort($tmpArr, SORT_STRING);

        return (sha1(implode($tmpArr)) === $signature) ? $echostr : 'Fail';
    }


    /**
     * 回复信息模版，MsgType以下部分自行定义
     * @param string $type
     * @return array
     */
    private function _Reply_Template($type = 'text')
    {
        $xml = array();
        $xml['ToUserName'] = $this->OpenID;
        $xml['FromUserName'] = $this->mpp['mppRealID'];//系统微信号
        $xml['CreateTime'] = time();
        $xml['MsgType'] = $type;
        return $xml;
    }

}