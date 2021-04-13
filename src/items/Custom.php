<?php

namespace esp\weiXin\items;

use esp\weiXin\Base;
use esp\weiXin\Send;

final class Custom extends Base implements Send
{
    private $custom;

    /**
     * 指定客服
     * @param string $account
     * @return $this
     */
    public function Custom(string $account)
    {
        $this->custom = $account;
        return $this;
    }


    /**
     * @param array $content
     * @param array $option
     * @return bool|mixed|string
     * @throws \Exception
     *
     * 公众号可以发所有消息，
     *
     * 小程序里的客服只可以发送：
     * text    文本消息
     * image    图片消息
     * link    图文链接
     * miniprogrampage    小程序卡片
     *
     */
    public function send(array $content, array $option = [])
    {
        if ($content['type'] === 'arts') return "多图文消息暂不支持预览";

        $data = $this->{$content['type']}($content);

        $data['touser'] = $this->openID;
        $data['msgtype'] = $content['type'];

        if ($data['msgtype'] === 'news') $data['msgtype'] = 'mpnews';
        else if ($data['msgtype'] === 'article') $data['msgtype'] = 'news';
        else if ($data['msgtype'] === 'menu') $data['msgtype'] = 'msgmenu';
        else if ($data['msgtype'] === 'app') $data['msgtype'] = 'miniprogrampage';
        else if ($data['msgtype'] === 'ask') $data['msgtype'] = 'msgmenu';

        if (!empty($this->custom)) {
            $data['customservice'] = ['kf_account' => $this->custom];
        }

        return $this->post($data, $option);
    }

    /**
     * 直接发送在其他地方已组织好的内容
     * @param array $data
     * @param array $option
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function post(array $data, array $option = [])
    {
        $api = "/cgi-bin/message/custom/send?access_token={access_token}";
        $rest = $this->Request($api, $data, $option);
        if (is_string($rest)) return $rest;
        if (strtolower($rest['errmsg'] ?? '') === 'ok') return true;
        return $rest['errmsg'] ?? ($rest['menuid'] ?? 'OK');
    }


    /**
     * 文本回复
     * @param array $content
     * @return array
     */
    public function Text(array $content)
    {
        $reply = [];
        $reply['text'] = [];

        $v = [];
        $v['href'] = "";
        $v['appid'] = "";
        $v['path'] = "";
        $v['text'] = $content['text']['desc'] ?? ($content['text'] ?? '');

        if (!empty($content['link'])) {
            $v['href'] = " href='{$content['link']}'";
        }
        if (!empty($content['app']['appid'])) {
            $v['appid'] = " data-miniprogram-appid='{$content['app']['appid']}'";
            $v['path'] = " data-miniprogram-path='{$content['app']['path']}'";
        }
        if (!empty($content['link']) or !empty($content['app']['appid'])) {
            $reply['text']['content'] = sprintf('%s%s%s%s>%s<%s', '<a', $v['href'], $v['appid'], $v['path'], $v['text'], '/a>');
        } else {
            $reply['text']['content'] = $content['text']['desc'];
        }
        return $reply;
    }

    public function Image(array $content)
    {
        $reply = [];
        $reply['image'] = ['media_id' => $content['image']['id'] ?? ($content['image'] ?? '')];
        return $reply;
    }

    public function Ask(array $content)
    {
        $ask = $content['ask'];
        $cond = [];
        foreach ($ask['content'] as $i => $cont) {
            $cond[] = [
                'id' => $ask['id'] . substr("0{$i}", -2),
                'content' => '(' . ($i + 1) . ') ' . $cont['title']
            ];
        }

        $reply = [];
        $reply['msgmenu'] = [
            'head_content' => $ask['head'],
            'tail_content' => $ask['tail'],
            'list' => $cond,
        ];
        return $reply;
    }


    public function App(array $content)
    {
        $reply = [];
        $reply['miniprogrampage'] = [
            'title' => $content['text']['title'],
            'appid' => $content['app']['appid'],
            'path' => $content['app']['path'],
            'thumb_media_id' => $content['image']['id'],
        ];
        return $reply;
    }


    public function Voice(array $content)
    {
        $reply = [];
        $reply['voice'] = ['media_id' => $content['voice']['id']];
        return $reply;
    }

    public function Video(array $content)
    {
        $reply = [];
        $reply['video'] = [
            'media_id' => $content['video']['id'],
            'thumb_media_id' => $content['image']['id'],
            'title' => $content['text']['title'],
            'description' => $content['text']['desc'],
        ];
        return $reply;
    }


    public function Music(array $data)
    {
        $reply = [];
        $reply['music'] = [];
        $reply['music']['title'] = $data['music']['title'];
        $reply['music']['description'] = $data['music']['desc'];
        $reply['music']['musicurl'] = $data['music']['url'];
        $reply['music']['hqmusicurl'] = $data['music']['hq'];
        $reply['music']['thumb_media_id'] = $data['image']['id'];
        return $reply;
    }

    public function Article(array $data)
    {
        $art = [];
        $art['title'] = $data['text']['title'];
        $art['description'] = $data['text']['desc'];
        $art['picurl'] = $data['image']['path'];
        $art['url'] = $data['link'];

        $reply = [];
        $reply['news'] = [];
        $reply['news']['articles'] = [$art];
        return $reply;
    }

    public function News(array $content)
    {
        $reply = [];
        $reply['mpnews']['media_id'] = $content['news']['id'];
        return $reply;
    }

    public function Menu(array $content)
    {
        $reply = [];
        $reply['msgmenu'] = [];
        $reply['msgmenu']['head_content'] = $content['menu']['head'];
        $reply['msgmenu']['tail_content'] = $content['menu']['tail'];
        $reply['msgmenu']['list'] = $content['menu']['list'];
        return $reply;
    }


}