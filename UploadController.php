<?php
declare(strict_types=1);

namespace nova\plugin\upload;

use nova\framework\http\Request;
use nova\framework\http\Response;

class UploadController
{
    public static function upload(Request $request,array $allowedTypes = ["images" =>['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff']]): Response
    {
        $uploadDir = ROOT_PATH.DS."uploads".DS;
        $types = [];
        foreach ($allowedTypes as $type => $exts) {
            $types = array_merge($types, $exts);
        }
        $maxFileSize = 0;
        $upload = new UploadHandler($uploadDir, $types, $maxFileSize);
        try {
            $unique = $request->post('unique', "");
            if ($unique == "") {
                $unique = md5(uniqid((string)mt_rand(), true));
            }
            // 判断是否为32位md5
            if (!preg_match('/^[a-f0-9]{32}$/', $unique)) {
                return Response::asJson(["code" => 400,"msg" => "唯一标识符错误"]);
            }
            $result = $upload->handleUpload($unique);
            if ($result != null) {
                $upload->cleanUpUseLessTempDir();
                FileDao::getInstance()->insertModel($result);
                FileDao::getInstance()->removeTempFiles();

                return Response::asJson(["code" => 200,"msg" => "上传成功","data" => $result->uri_name]);
            }
            return Response::asJson(["code" => 201,"msg" => "上传中","data" => $unique]);
        } catch (UploadException $e) {
            return Response::asJson(["code" => 400,"msg" => $e->getMessage()]);
        }
    }
    public function delete($name,$link_id): Response
    {
        $file = FileDao::getInstance()->getFile($name);
        if ($file->link_id != $link_id) {
            return Response::asJson(["code" => 403,"msg" => "禁止删除"]);
        }
        FileDao::getInstance()->removeFile($name);
        return Response::asJson(["code" => 200,"msg" => "删除成功"]);
    }

    public function file(string $name): Response
    {
        $name = preg_replace('/^(\d{4})(\d{2})(\d{2})-/', '$1/$2/$3/', $name);
        $file = ROOT_PATH . '/uploads/' . $name;
        if (file_exists($file) && is_file($file)) {
            return Response::asStatic($file);
        } else {
            return Response::asText("404 not found");
        }
    }
}