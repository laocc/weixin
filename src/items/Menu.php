<?php

namespace esp\weixin\items;

use esp\weixin\Base;

final class Menu extends Base
{

    /**
     * 上传自定义菜单
     * @param $data
     * @return array|mixed|string
     * @throws \Exception
     */
    public function create($data)
    {
        if ($disable = $this->mppAuth([1])) return $disable;

        //https://api.weixin.qq.com/cgi-bin/menu/addconditional?access_token=ACCESS_TOKEN
        if (isset($data['matchrule'])) {
            $api = "/cgi-bin/menu/addconditional?access_token={access_token}";
        } else {
            $api = "/cgi-bin/menu/create?access_token={access_token}";
        }
        $rest = $this->Request($api, $data);
        if (is_string($rest)) return $rest;
        return $rest['errmsg'] ?? ($rest['menuid'] ?? 'OK');
    }

    /**
     * 删除菜单
     * @param int $menuIndex
     * @return array|mixed|string
     * @throws \Exception
     */
    public function delete(int $menuIndex)
    {
        if ($disable = $this->mppAuth([1])) return $disable;

        //https://api.weixin.qq.com/cgi-bin/menu/delconditional?access_token=ACCESS_TOKEN
        if ($menuIndex) {
            $api = "/cgi-bin/menu/delconditional?access_token={access_token}";
            $rest = $this->Request($api, ['menuid' => $menuIndex]);
        } else {
            $api = "/cgi-bin/menu/delete?access_token={access_token}";
            $rest = $this->Request($api);
        }
        if (is_string($rest)) return $rest;
        return $rest['errmsg'];
    }


    /**
     * 获取微信菜单,取决于$info
     * @return mixed|string
     * https://mp.weixin.qq.com/wiki/16/ff9b7b85220e1396ffa16794a9d95adc.html
     * @return array|mixed|string
     * @throws \Exception
     */
    public function load()
    {
        if ($disable = $this->mppAuth([1])) return $disable;

        $api = "/cgi-bin/menu/get?access_token={access_token}";
        $JsonStr = $this->Request($api);
        return $JsonStr;
    }


}