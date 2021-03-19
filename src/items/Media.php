<?php

namespace esp\weiXin\items;

use esp\weiXin\Base;

final class Media extends Base
{


    public function article($data)
    {
        $api = "/cgi-bin/material/add_news?access_token={access_token}";
        $rest = $this->Request($api, $data);
        return $rest;
    }


    public function list(string $type, int $index)
    {
        $api = "/cgi-bin/material/batchget_material?access_token={access_token}";
        $data = ['type' => $type, 'offset' => $index * 20, 'count' => 20];
        $rest = $this->Request($api, $data);
        return $rest;
    }


    public function download(string $medIndex)
    {
        //https://api.weixin.qq.com/cgi-bin/material/get_material?access_token=ACCESS_TOKEN
        $api = "/cgi-bin/material/get_material?access_token={access_token}";
        $rest = $this->Request($api, ['media_id' => $medIndex]);
        return $rest;
    }


    public function upload(string $type, string $file, array $info = [])
    {
        $api = "/cgi-bin/material/add_material?access_token={access_token}&type={$type}";

        $option = [];
        $option['type'] = 'upload';

        $file = realpath($file);
        $name = pathinfo($file, PATHINFO_BASENAME);

        $data = [];
        $data["media"] = new \CURLFile($file);
        $data["media"]->postname = $name;

        if ($type === 'video') {
            $data['description'] = [
                'title' => $info['title'] ?? $name,
                'introduction' => $info['title'] ?? $name
            ];
            $data['description'] = json_encode($data['description'], 256 | 64);
        }

        $rest = $this->Request($api, $data, $option);
        return $rest;
    }

    public function delete($mediaID)
    {
        $api = "/cgi-bin/material/del_material?access_token={access_token}";
        $rest = $this->Request($api, ['media_id' => $mediaID]);
        if (is_string($rest)) return $rest;
        return $rest['errmsg'] === 'ok';
    }


}