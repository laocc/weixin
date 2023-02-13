<?php

namespace esp\weiXin\items;

use esp\http\HttpResult;
use Exception;

final class Media extends _Base
{

    public function article($data)
    {
        $api = "/cgi-bin/material/add_news?access_token={access_token}";
        return $this->Request($api, $data);
    }


    public function list(string $type, int $index)
    {
        $api = "/cgi-bin/material/batchget_material?access_token={access_token}";
        $data = ['type' => $type, 'offset' => $index * 20, 'count' => 20];
        return $this->Request($api, $data);
    }


    public function download(string $medIndex)
    {
        $api = "/cgi-bin/material/get_material?access_token={access_token}";
        return $this->Request($api, ['media_id' => $medIndex]);
    }


    /**
     * @param string $type
     * @param string $file
     * @param array $info
     * @return array|HttpResult|mixed|string|null
     * @throws Exception
     */
    public function upload(string $type, string $file, array $info = [])
    {
        $api = "/cgi-bin/material/add_material?access_token={access_token}&type={$type}";
        if (substr($type, 0, 5) === 'temp_') {
            $type = substr($type, 5);
            $api = "/cgi-bin/media/upload?access_token={access_token}&type={$type}";
            if (!in_array($type, ['image', 'voice', 'video', 'thumb'])) {
                return "临时素材仅支持('image','voice','video','thumb')格式";
            }
        }

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

        return $this->Request($api, $data, $option);
    }

    public function delete($mediaID)
    {
        $api = "/cgi-bin/material/del_material?access_token={access_token}";
        $rest = $this->Request($api, ['media_id' => $mediaID]);
        if (is_string($rest)) return $rest;
        return ($rest['errmsg'] ?? '') === 'ok';
    }


}