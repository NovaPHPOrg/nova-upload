<?php

declare(strict_types=1);

namespace nova\plugin\upload;

use nova\framework\core\File;
use nova\framework\core\Instance;
use nova\framework\http\Request;
use nova\framework\http\Response;

class UploadController extends Instance
{
    private string $uploadDir;
    private string $tableName;

    public function __construct(string $uploadDir = null, string $tableName = '')
    {
        $this->uploadDir = $uploadDir ?? ROOT_PATH . DS . "uploads" . DS;
        $this->tableName = $tableName;

        File::mkDir($this->uploadDir);
    }

    /**
     * @param array $allowedTypes 允许的扩展名分组，如 ['images' => ['png','jpg']]
     * @param callable|null $onSuccess 上传完成回调，签名：function(FileModel $file): void
     */
    public function upload(
        Request $request,
        array $allowedTypes = ["images" => ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff']],
        ?callable $onSuccess = null
    ): Response {
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

                if ($onSuccess !== null) {
                    $onSuccess($result);
                }

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
        $file = $this->dir($name);
        if (file_exists($file) && is_file($file)) {
            return Response::asStatic($file);
        }
        return Response::asText("404 not found");
    }

    public function dir(string $name): string
    {
        $name = preg_replace('/^(\d{4})(\d{2})(\d{2})-/', '$1/$2/$3/', $name);
        $file = rtrim($this->uploadDir, DS) . DS . $name;
        return $file;
    }

    public function useFile(string $name, string $link_id): void
    {
        FileDao::getInstance($this->tableName)->useFile($name, $link_id);
    }

    public function useMarkdownFile(string $name, string $link_id): void
    {
        FileDao::getInstance($this->tableName)->useMarkdownFile($name, $link_id);
    }

    public function useHtmlFile(string $name, string $link_id): void
    {
        FileDao::getInstance($this->tableName)->useHtmlFile($name, $link_id);
    }

    public function getUploadDir(): string
    {
        return $this->uploadDir;
    }
}
