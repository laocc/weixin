<?php

namespace esp\weixin\items;

use esp\weixin\Base;
use esp\weixin\Send;

final class Custom extends Base implements Send
{
    private $openID;
    private $custom;

    public function setFans(string $openID, string $nick)
    {
        $this->openID = $openID;
        $this->setNick($nick);
        return $this;
    }


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

        $api = "/cgi-bin/message/custom/send?access_token={access_token}";
        $rest = $this->Request($api, $data);
        if (is_string($rest)) return $rest;
        if (strtolower($rest['errmsg']) === 'ok') return true;
        return $rest['errmsg'] ?? ($rest['menuid'] ?? 'OK');
    }

    /**
     * 直接发送在其他地方已组织好的内容
     */
    public function post($data)
    {
        $api = "/cgi-bin/message/custom/send?access_token={access_token}";
        $rest = $this->Request($api, $data);
        if (is_string($rest)) return $rest;
        if (strtolower($rest['errmsg']) === 'ok') return true;
        return $rest['errmsg'] ?? ($rest['menuid'] ?? 'OK');
    }


    /**
     * 文本回复
     * @param array $option
     * @return array
     */
    public function Text(array $option)
    {
        $reply = [];
        $reply['text'] = [];

        $opt = [];
        $opt['href'] = "";
        $opt['appid'] = "";
        $opt['path'] = "";
        $opt['text'] = $option['text']['desc'];

        if (!empty($option['link'])) {
            $opt['href'] = " href='{$option['link']}'";
        }
        if (!empty($option['app']['appid'])) {
            $opt['appid'] = " data-miniprogram-appid='{$option['app']['appid']}'";
            $opt['path'] = " data-miniprogram-path='{$option['app']['path']}'";
        }
        if (!empty($option['link']) or !empty($option['app']['appid'])) {
            $reply['text']['content'] = \esp\helper\replace_array('<a{href}{appid}{path}>{text}</a>', $opt);
        } else {
            $reply['text']['content'] = $option['text']['desc'];
        }
        return $reply;
    }

    public function Image(array $option)
    {
        $reply = [];
        $reply['image'] = ['media_id' => $option['image']['id']];
        return $reply;
    }

    public function Ask(array $option)
    {
        $askID = intval($option['ask']['id']);
        $modAsc = new AskModel();
        $ask = $modAsc->get($askID);
        $ask['askContent'] = json_decode($ask['askContent'], true);

        $cond = [];
        foreach ($ask['askContent'] as $i => $cont) {
            $cond[] = ['id' => $askID . substr("0{$i}", -2), 'content' => '(' . ($i + 1) . ') ' . $cont['title']];
        }

        $reply = [];
        $reply['msgmenu'] = [
            'head_content' => $ask['askTitle'],
            'tail_content' => $ask['askFoot'],
            'list' => $cond,
        ];
        return $reply;
    }


    public function App(array $option)
    {
        $reply = [];
        $reply['miniprogrampage'] = [
            'title' => $option['text']['title'],
            'appid' => $option['app']['appid'],
            'path' => $option['app']['path'],
            'thumb_media_id' => $option['image']['id'],
        ];
        return $reply;
    }


    public function Voice(array $option)
    {
        $reply = [];
        $reply['voice'] = ['media_id' => $option['voice']['id']];
        return $reply;
    }

    public function Video(array $option)
    {
        $reply = [];
        $reply['video'] = [
            'media_id' => $option['video']['id'],
            'thumb_media_id' => $option['image']['id'],
            'title' => $option['text']['title'],
            'description' => $option['text']['desc'],
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

    public function News(array $option)
    {
        $reply = [];
        $reply['mpnews']['media_id'] = $option['news']['id'];
        return $reply;
    }

    public function Menu(array $option)
    {
        $reply = [];
        $reply['msgmenu'] = [];
        $reply['msgmenu']['head_content'] = $option['menu']['head'];
        $reply['msgmenu']['tail_content'] = $option['menu']['tail'];
        $reply['msgmenu']['list'] = $option['menu']['list'];
        return $reply;
    }


}