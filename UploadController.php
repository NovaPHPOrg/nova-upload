<?php

declare(strict_types=1);

namespace nova\plugin\upload;

use nova\framework\core\File;
use nova\framework\http\Request;
use nova\framework\http\Response;

class UploadController
{
    private string $uploadDir;
    private string $tableName;
    private static ?self $defaultInstance = null;

    public function __construct(string $uploadDir = null,string $tableName = null)
    {
        $this->uploadDir = $uploadDir ?? ROOT_PATH . DS . "uploads" . DS;
        $this->tableName = $tableName;

        File::mkDir($this->uploadDir);
    }

    public static function getInstance(string $uploadDir = null): self
    {
        if (self::$defaultInstance === null || $uploadDir !== null) {
            self::$defaultInstance = new self($uploadDir);
        }
        return self::$defaultInstance;
    }

    public function upload(Request $request, array $allowedTypes = ["images" => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff']]): Response
    {
        $types = [];
        foreach ($allowedTypes as $type => $exts) {
            $types = array_merge($types, $exts);
        }
        $maxFileSize = 0;
        $upload = new UploadHandler($this->uploadDir, $types, $maxFileSize);
        try {
            $unique = $request->post('unique', "");
            if ($unique === "") {
                $unique = md5(uniqid((string)mt_rand(), true));
            }
            // 判断是否为32位md5
            if (!preg_match('/^[a-f0-9]{32}$/', $unique)) {
                return Response::asJson(["code" => 400, "msg" => "唯一标识符错误"]);
            }
            $result = $upload->handleUpload($unique);
            if ($result !== null) {
                $upload->cleanUpUseLessTempDir();
                FileDao::getInstance($this->tableName)->insertModel($result);
                FileDao::getInstance($this->tableName)->removeTempFiles();

                return Response::asJson(["code" => 200, "msg" => "上传成功", "data" => $result->uri_name]);
            }
            return Response::asJson(["code" => 201, "msg" => "上传中", "data" => $unique]);
        } catch (UploadException $e) {
            return Response::asJson(["code" => 400, "msg" => $e->getMessage()]);
        }
    }

    public function delete($name, $link_id): Response
    {
        $file = FileDao::getInstance($this->tableName)->getFile($name);
        if ($file && $file->link_id != $link_id) {
            return Response::asJson(["code" => 403, "msg" => "禁止删除"]);
        }
        FileDao::getInstance()->removeFile($name);
        return Response::asJson(["code" => 200, "msg" => "删除成功"]);
    }

    public function file(string $name): Response
    {
        $name = preg_replace('/^(\d{4})(\d{2})(\d{2})-/', '$1/$2/$3/', $name);
        $file = rtrim($this->uploadDir, DS) . DS . $name;
        if (file_exists($file) && is_file($file)) {
            return Response::asStatic($file);
        }
        return Response::asText("404 not found");
    }

    public function useFile(string $name,string $link_id): void
    {
        FileDao::getInstance($this->tableName)->useFile($name,$link_id);
    }

    public function useMarkdownFile(string $name,string $link_id): void
    {
        FileDao::getInstance($this->tableName)->useMarkdownFile($name,$link_id);
    }

    public function useHtmlFile(string $name,string $link_id): void
    {
        FileDao::getInstance($this->tableName)->useHtmlFile($name,$link_id);
    }

    public function getUploadDir(): string
    {
        return $this->uploadDir;
    }
}
