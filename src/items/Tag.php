<?php

namespace esp\weiXin\items;

use Exception;

final class Tag extends _Base
{

    //(48001)api unauthorized rid: 5f591bf1-7d33c952-38db6b0e

    public function sync()
    {
        if ($disable = $this->mppAuth([1, 4])) return $disable;

        $api = "/cgi-bin/tags/get?access_token={access_token}";
        $rest = $this->Request($api);
        if (is_string($rest)) return $rest;
        return ($rest['tags']);
    }

    /**
     * 打标
     *
     * @param array $openID
     * @param int $tagID
     * @return false|mixed|string
     * @throws Exception
     */
    public function batch(array $openID, int $tagID)
    {
        if ($disable = $this->mppAuth([1, 4])) return $disable;
        $api = "/cgi-bin/tags/members/batchtagging?access_token={access_token}";
        $rest = $this->Request($api, ['openid_list' => $openID, 'tagid' => $tagID]);
        if (is_string($rest)) return $rest;
        return $rest['errcode'] === 0;
    }

    /**
     * 将用户从群组移出
     *
     * @param array $openID
     * @param int $tagID
     * @return false|mixed|string
     * @throws Exception
     */
    public function move(array $openID, int $tagID)
    {
        if ($disable = $this->mppAuth([1, 4])) return $disable;
        $api = "/cgi-bin/tags/members/batchuntagging?access_token={access_token}";
        $rest = $this->Request($api, ['openid_list' => $openID, 'tagid' => $tagID]);
        if (is_string($rest)) return $rest;
        return $rest['errcode'] === 0;
    }

    public function create(string $name)
    {
        if ($disable = $this->mppAuth([1, 4])) return $disable;

        $api = "/cgi-bin/tags/create?access_token={access_token}";
        $rest = $this->Request($api, ['tag' => ['name' => $name]]);
        if (is_string($rest)) return $rest;
        return intval($rest['tag']['id'] ?? 0);
    }

    /**
     * 5. 获取标签下粉丝列表
     * @param string $name
     * @return array|bool|int|mixed|string
     * @throws Exception
     */
    public function load(string $name)
    {
        if ($disable = $this->mppAuth([1, 4])) return $disable;
        //待完善
//
//        $api = "/cgi-bin/user/tag/get?access_token={access_token}";
//        $rest = $this->Request($api, ['tag' => ['name' => $name]]);
//        if (is_string($rest)) return $rest;
//        return intval($rest['tag']['id'] ?? 0);
    }

    public function delete(int $id)
    {
        if ($disable = $this->mppAuth([1, 4])) return $disable;

        if ($id < 5) return "系统默认标签，不能删除";
        $api = "/cgi-bin/tags/delete?access_token={access_token}";
        $rest = $this->Request($api, ['tag' => ['id' => $id]]);
        if (is_string($rest)) return $rest;
        return ($rest['errmsg'] === 'ok');
    }

    public function update(int $id, string $name)
    {
        if ($disable = $this->mppAuth([1, 4])) return $disable;

        if ($id < 5) return "系统默认标签，不能编辑";
        $api = "/cgi-bin/tags/update?access_token={access_token}";
        $rest = $this->Request($api, ['tag' => ['id' => $id, 'name' => $name]]);
        if (is_string($rest)) return $rest;
        return $rest['errmsg'] === 'ok';
    }

}