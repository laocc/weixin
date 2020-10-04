<?php

namespace laocc\weixin\items;


final class Tag extends Base
{

    //(48001)api unauthorized rid: 5f591bf1-7d33c952-38db6b0e

    public function sync()
    {
        if ($disable = $this->wx->mppAuth([1, 4])) return $disable;

        $api = "/cgi-bin/tags/get?access_token={access_token}";
        $rest = $this->wx->Request($api);
        if (is_string($rest)) return $rest;
        return ($rest['tags']);
    }

    public function batch(array $openID, int $tagID)
    {
        if ($disable = $this->wx->mppAuth([1, 4])) return $disable;
        $api = "/cgi-bin/tags/members/batchtagging?access_token={access_token}";
        $rest = $this->wx->Request($api, ['openid_list' => $openID, 'tagid' => $tagID]);
        if (is_string($rest)) return $rest;
        return $rest['errmsg'];
    }

    public function create(string $name)
    {
        if ($disable = $this->wx->mppAuth([1, 4])) return $disable;

        $api = "/cgi-bin/tags/create?access_token={access_token}";
        $rest = $this->wx->Request($api, ['tag' => ['name' => $name]]);
        if (is_string($rest)) return $rest;
        return intval($rest['tag']['id'] ?? 0);
    }

    /**
     * 5. 获取标签下粉丝列表
     * @param string $name
     * @return array|bool|int|mixed|string
     * @throws \Exception
     */
    public function load(string $name)
    {
        if ($disable = $this->wx->mppAuth([1, 4])) return $disable;
        //待完善
//
//        $api = "/cgi-bin/user/tag/get?access_token={access_token}";
//        $rest = $this->wx->Request($api, ['tag' => ['name' => $name]]);
//        if (is_string($rest)) return $rest;
//        return intval($rest['tag']['id'] ?? 0);
    }

    public function delete(int $id)
    {
        if ($disable = $this->wx->mppAuth([1, 4])) return $disable;

        if ($id < 5) return "系统默认标签，不能删除";
        $api = "/cgi-bin/tags/delete?access_token={access_token}";
        $rest = $this->wx->Request($api, ['tag' => ['id' => $id]]);
        if (is_string($rest)) return $rest;
        return ($rest['errmsg'] === 'ok');
    }

    public function update(int $id, string $name)
    {
        if ($disable = $this->wx->mppAuth([1, 4])) return $disable;

        if ($id < 5) return "系统默认标签，不能编辑";
        $api = "/cgi-bin/tags/update?access_token={access_token}";
        $rest = $this->wx->Request($api, ['tag' => ['id' => $id, 'name' => $name]]);
        if (is_string($rest)) return $rest;
        return $rest['errmsg'] === 'ok';
    }

}