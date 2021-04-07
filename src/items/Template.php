<?php

namespace esp\weiXin\items;

use esp\weiXin\Base;
use esp\weiXin\Send;

final class Template extends Base implements Send
{

    private $openID;

    public function setFans(string $openID, string $nick)
    {
        $this->openID = $openID;
        $this->setNick($nick);
        return $this;
    }

    /**
     * 发送消息
     * @param array $content
     * @param array $option
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    public function send(array $content, array $option = [])
    {
        $value = [];
        $value['touser'] = $this->openID;
        $value['template_id'] = $content['template']['key'];
        $value['url'] = $content['link'];
        $value['miniprogram']['appid'] = $content['miniprogram']['appid'];
        $value['miniprogram']['pagepath'] = $content['miniprogram']['path'];
        $value['miniprogram']['pagepath'] = $content['miniprogram']['path'];

        $value['data'] = [];
        foreach ($content['template']['params'] as $k => $v) {
            $value['data'][$k] = ['value' => $v, 'color' => $content['template']['colors'][$k]];
        }

        $api = "/cgi-bin/message/template/send?access_token={access_token}";
        $get = $this->Request($api, $value);
        if (is_string($get)) return $get;
        if (strtolower($get['errmsg'] ?? '') === 'ok') return true;
        return $get['errmsg'];
    }


    /**
     * 从行业模板库选择模板到帐号后台，获得模板ID的过程可在微信公众平台后台完成
     * @param string $short 模板库中模板的编号，有“TM**”和“OPENTMTM**”等形式
     * @return array|mixed|string
     * @throws \Exception
     * iPk5sOIt5X_flOVKn5GrTFpncEYTojx6ddbt8WYoV5s
     */
    public function register(string $short)
    {
        $api = "/cgi-bin/template/api_add_template?access_token={access_token}";
        $data = ['template_id_short' => $short];
        $get = $this->Request($api, $data);
        if (is_string($get)) return $get;
        return $get;
    }


    /**
     * 获取已添加至帐号下所有模板列表
     * @return array|string
     * @throws \Exception
     */
    public function download()
    {
        $api = "/cgi-bin/template/get_all_private_template?access_token={access_token}";
        $get = $this->Request($api);
        if (is_string($get)) return $get;
        return $get['template_list'];
    }


    /**
     * 获取设置的行业信息
     * @return array|mixed|string
     * @throws \Exception
     */
    public function getIndustry()
    {
        $api = "/cgi-bin/template/get_industry?access_token={access_token}";
        $get = $this->Request($api);
        if (is_string($get)) return $get;
        return $get;
    }

    /**
     * 设置行业
     * @param $industry1
     * @param $industry2
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    public function setIndustry($industry1, $industry2)
    {
        $api = "/cgi-bin/template/api_set_industry?access_token={access_token}";
        $data = [];
        $data['industry_id1'] = $industry1;
        $data['industry_id2'] = $industry2;
        $get = $this->Request($api, $data);
        if (is_string($get)) return $get;
        return $get['errmsg'] === 'ok';
    }


    /**
     * 删除模板
     * @param string $tmpID
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    public function delete(string $tmpID)
    {
        $api = "/cgi-bin/template/del_private_template?access_token={access_token}";
        $data = ['template_id' => $tmpID];
        $get = $this->Request($api, $data);
        if (is_string($get)) return $get;
        return $get['errmsg'] === 'ok';
    }


    public function industry()
    {
        $industry = [];
        $industry[1] = ['IT科技', '互联网/电子商务'];
        $industry[2] = ['IT科技', 'IT软件与服务'];
        $industry[3] = ['IT科技', 'IT硬件与设备'];
        $industry[4] = ['IT科技', '电子技术'];
        $industry[5] = ['IT科技', '通信与运营商'];
        $industry[6] = ['IT科技', '网络游戏'];
        $industry[7] = ['金融业', '银行'];
        $industry[8] = ['金融业', '基金理财信托'];
        $industry[9] = ['金融业', '保险'];
        $industry[10] = ['餐饮', '餐饮'];
        $industry[11] = ['酒店旅游', '酒店'];
        $industry[12] = ['酒店旅游', '旅游'];
        $industry[13] = ['运输与仓储', '快递'];
        $industry[14] = ['运输与仓储', '物流'];
        $industry[15] = ['运输与仓储', '仓储'];
        $industry[16] = ['教育', '培训'];
        $industry[17] = ['教育', '院校'];
        $industry[18] = ['政府与公共事业', '学术科研'];
        $industry[19] = ['政府与公共事业', '交警'];
        $industry[20] = ['政府与公共事业', '博物馆'];
        $industry[21] = ['政府与公共事业', '公共事业非盈利机构'];
        $industry[22] = ['医药护理', '医药医疗'];
        $industry[23] = ['医药护理', '护理美容'];
        $industry[24] = ['医药护理', '保健与卫生'];
        $industry[25] = ['交通工具', '汽车相关'];
        $industry[26] = ['交通工具', '摩托车相关'];
        $industry[27] = ['交通工具', '火车相关'];
        $industry[28] = ['交通工具', '飞机相关'];
        $industry[29] = ['房地产', '建筑'];
        $industry[30] = ['房地产', '物业'];
        $industry[31] = ['消费品', '消费品'];
        $industry[32] = ['商业服务', '法律'];
        $industry[33] = ['商业服务', '会展'];
        $industry[34] = ['商业服务', '中介服务'];
        $industry[35] = ['商业服务', '认证'];
        $industry[36] = ['商业服务', '审计'];
        $industry[37] = ['文体娱乐', '传媒'];
        $industry[38] = ['文体娱乐', '体育'];
        $industry[39] = ['文体娱乐', '娱乐休闲'];
        $industry[40] = ['印刷', '印刷'];
        $industry[41] = ['其它', '其它'];
        return $industry;
    }
}