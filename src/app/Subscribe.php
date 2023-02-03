<?php

namespace esp\weiXin\app;

use esp\weiXin\Base;

/**
 * 小程序订阅消息
 * Class Subscribe
 * @package esp\weiXin\items
 */
class Subscribe extends Base
{

    /**
     * 发送
     * @param string $openID
     * @param string $tempID
     * @param array $params
     * @param string $page
     * @return array|bool|string
     *
     * 订阅
     * https://developers.weixin.qq.com/miniprogram/dev/framework/open-ability/subscribe-message.html
     * https://developers.weixin.qq.com/miniprogram/dev/api/open-api/subscribe-message/wx.requestSubscribeMessage.html
     * 发送
     * https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/subscribe-message/subscribeMessage.send.html
     */
    public function sendSubscribeMsg(string $openID, string $tempID, array $params, string $page)
    {
        $param = [];
//        foreach ($params as $k => $v) $param[$k] = ['value' => $v];
        foreach ($params as $k => $v) $param[$k] = ['value' => $v];

        $data = [];
        $data['touser'] = $openID;
        $data['template_id'] = $tempID;
        $data['lang'] = 'zh_CN';
        $data['page'] = $page;
//        $data['miniprogram_state'] = 'trial';
        $data['data'] = $param;
        $api = "/cgi-bin/message/subscribe/send?access_token={access_token}";
        $rest = $this->Request($api, $data);
        if (is_string($rest)) return $rest;
        if (strtolower($rest['errmsg'] ?? '') === 'ok') return true;
        return $rest['errmsg'] ?? 'ok';
    }

    /**
     * 获取模板标题下的关键词列表,不知道有什么用处
     * @param string $tempID
     * @return array|bool|string
     * https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/subscribe-message/subscribeMessage.getPubTemplateKeyWordsById.html
     *
     */
    public function getTemplateKeyword(string $tempID)
    {
        $api = "/wxaapi/newtmpl/getpubtemplatekeywords?access_token={access_token}&tid={$tempID}";
        $rest = $this->Request($api);
        if (is_string($rest)) return $rest;
        return $rest['data'];
    }

    /**
     * 获取所有已注册的模版
     * @param string $tempID
     * @return array|\esp\http\HttpResult|mixed|string
     * @throws \Exception
     */
    public function getAllTemplate(string $tempID = '')
    {
        $api = "/wxaapi/newtmpl/gettemplate?access_token={access_token}";
        $rest = $this->Request($api);
        if (is_string($rest)) return $rest;
        if (empty($tempID)) {
            $template = [];
            foreach ($rest['data'] as $temp) {
                $params = [];
                $content = explode("\n", $temp['content']);
                foreach ($content as $cont) {
                    if (empty($cont)) break;
                    $lin = explode(':', $cont);
                    $params[str_replace(['{{', '}}', '.DATA'], '', $lin[1])] = $lin[0];
                }

                $template[] = [
                    'title' => $temp['title'],
                    'tempID' => $temp['priTmplId'],
                    'params' => $params,
                ];
            }

            return $template;
        }

        foreach ($rest['data'] as $temp) {
            if ($temp['priTmplId'] === $tempID) {
                $params = [];
                $content = explode("\n", $temp['content']);
                foreach ($content as $cont) {
                    if (empty($cont)) break;
                    $lin = explode(':', $cont);
                    $params[str_replace(['{{', '}}', '.DATA'], '', $lin[1])] = $lin[0];
                }
                return $params;
            }
        }
        return "未查询到指定的模版ID:{$tempID}";
    }

    /**
     * 下发小程序和公众号统一的服务消息
     *
     * @param string $openID
     * @param array $app
     * @param array $mpp
     * @return array|bool|\esp\http\HttpResult|mixed|string
     * @throws \Exception
     *
     * https://developers.weixin.qq.com/miniprogram/dev/api-backend/open-api/uniform-message/uniformMessage.send.html
     *
     */
    public function sendUniformMessage(string $openID, array $app, array $mpp)
    {
        $data = [];
        $data['touser'] = $openID;

        $data['weapp_template_msg'] = [];
        $data['weapp_template_msg']['template_id'] = $app['id'];
        $data['weapp_template_msg']['page'] = "init?expID=3";
        $data['weapp_template_msg']['form_id'] = 3333;
        $data['weapp_template_msg']['data'] = 3333;
        $data['weapp_template_msg']['emphasis_keyword'] = 3333;

        $data['mp_template_msg'] = [];
        $data['mp_template_msg']['appid'] = $this->mpp['mppid'];
        $data['mp_template_msg']['template_id'] = 3333;
        $data['mp_template_msg']['url'] = 3333;
        $data['mp_template_msg']['miniprogram'] = [];
        $data['mp_template_msg']['miniprogram']['appid'] = $this->mpp['appid'];
        $data['mp_template_msg']['miniprogram']['pagepath'] = 333;
        $data['mp_template_msg']['data'] = 3333;

        $api = "/cgi-bin/message/wxopen/template/uniform_send?access_token={access_token}";
        $rest = $this->Request($api, $data);
        if (is_string($rest)) return $rest;
        if (strtolower($rest['errmsg'] ?? '') === 'ok') return true;
        return $rest['errmsg'] ?? ($rest['menuid'] ?? 'OK');

    }
}