<?php

declare(strict_types=1);

namespace nova\plugin\upload;

use nova\framework\core\Context;
use nova\framework\core\File;
use nova\framework\core\Logger;

class UploadHandler
{
    private string $uploadDir;
    private array $allowedTypes;
    private int $maxFileSize;

    public function __construct($uploadDir = ROOT_PATH.DS."uploads".DS, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff'], $maxFileSize = 0)
    {
        $this->uploadDir = rtrim($uploadDir, DS) . DS;
        $this->allowedTypes = $allowedTypes;
        $this->maxFileSize = $maxFileSize;

        File::mkDir($this->uploadDir);
        if (rand(1, 100) === 1) {
            $this->cleanUpUseLessTempDir();
        }
    }

    /**
     * @throws UploadException
     */
    public function handleUpload($unique): ?FileModel
    {

        $request = Context::instance()->request();

        $chunkIndex = $request->post('chunkIndex', 0);
        $totalChunks = $request->post('totalChunks', 1);
        $fileName = $request->post('fileName', '');

        Logger::info('chunkIndex: ' . $chunkIndex);
        Logger::info('totalChunks: ' . $totalChunks);
        Logger::info('fileName: ' . $fileName);

        $uploadFile = $request->file('file');
        if (!$uploadFile) {
            $this->handleFileUploadError(0);
        }
        // Check if file was uploaded without errors
        if ($uploadFile->error !== UPLOAD_ERR_OK) {
            $this->handleFileUploadError($uploadFile->error);
        }

        // Validate file type
        if (!$this->isAllowedType($fileName)) {
            throw new UploadException('不允许的文件类型');
        }

        // Check file size
        if ($this->maxFileSize > 0 && $uploadFile->size > $this->maxFileSize) {
            throw new UploadException('文件过大');
        }

        $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
        $uniqueFileName = $unique;
        $uniqueFileNameExt  = $uniqueFileName.".". $fileExt;

        // Directory for storing temporary chunks
        $tempDir = $this->uploadDir . 'temp'.DS . $uniqueFileName.DS  ;
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // Store the current chunk
        $chunkPath = $tempDir . $chunkIndex;
        copy($uploadFile->tmp_name, $chunkPath);
        Logger::info("Chunk saved: $chunkPath");
        // Check if all chunks are uploaded
        if ($chunkIndex + 1 === $totalChunks) {
            Logger::info('All chunks uploaded');

            // Merge chunks into a single file
            $finalFilePath = $this->mergeChunks($tempDir, $uniqueFileNameExt, $totalChunks);
            Logger::info('Final file path: ' . $finalFilePath);

            return $this->finalFile($fileName, $tempDir, $finalFilePath, $uniqueFileName, $fileExt);
        } else {
            return null; // Indicate that more chunks are needed
        }
    }

    private function finalFile($fileName, $tempDir, $finalFilePath, $finalName, $ext): FileModel
    {
        // Get current date for directory structure
        $year = date('Y');
        $month = date('m');
        $day = date('d');

        // Final storage directory
        $dateDir = $this->uploadDir . $year . DS . $month . DS . $day . DS;
        if (!file_exists($dateDir)) {
            mkdir($dateDir, 0777, true);
        }

        // Move the merged file to the final directory
        $finalPath = $dateDir .$finalName.".{$ext}";
        rename($finalFilePath, $finalPath);

        // Create file model to return
        $fileModel = new FileModel();
        $fileModel->name = $fileName;
        $fileModel->size = filesize($finalPath);
        $fileModel->path = $finalPath;
        $fileModel->extension = $ext;
        $fileModel->uri_name = $year . $month . $day . "-" . $finalName.".{$ext}";
        $fileModel->is_temp = true;
        $fileModel->create_time = time();
        // Clean up temporary files
        $this->cleanUpTempDir($tempDir);

        return $fileModel;
    }

    public function handleFile($content, $ext): FileModel
    {
        $name = uniqid();
        $tempDir = $this->uploadDir . 'temp'.DS . $name.DS  ;
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $filename = "$name.$ext";
        $filepath = $tempDir . $filename;

        file_put_contents($filepath, $content);

        return $this->finalFile($filename, $tempDir, $filepath, $name, $ext);

    }

    /**
     * Merges all chunks into a single file.
     * @throws UploadException
     */
    private function mergeChunks(string $tempDir, string $finalFileName, int $total): string
    {
        $finalFilePath = $tempDir . $finalFileName;
        $outputFile = fopen($finalFilePath, 'wb');

        for ($i = 0; $i < $total; $i++) {

            $chunkFilePath = $tempDir . $i;
            if (file_exists($chunkFilePath)) {
                $chunkFile = fopen($chunkFilePath, 'rb');
                stream_copy_to_stream($chunkFile, $outputFile);
                fclose($chunkFile);
            } else {
                fclose($outputFile);
                throw new UploadException('缺少文件块' . $chunkFilePath . '，无法完成上传');
            }
        }

        fclose($outputFile);
        return $finalFilePath;
    }
    public function cleanUpUseLessTempDir(): void
    {
        $tempDir = $this->uploadDir . 'temp'.DS;
        if (!is_dir($tempDir)) {
            return;
        }
        $expiryHours = 24;
        $expiryTime = time() - ($expiryHours * 3600);

        foreach (scandir($tempDir) as $dir) {
            if ($dir !== '.' && $dir !== '..') {
                $dirPath = $tempDir . $dir;

                // Check if the directory has expired
                if (is_dir($dirPath) && filemtime($dirPath) < $expiryTime) {
                    $this->cleanUpTempDir($dirPath);
                }
            }
        }
    }

    /**
     * Deletes the temporary directory and its contents.
     */
    private function cleanUpTempDir(string $tempDir): void
    {
        foreach (scandir($tempDir) as $file) {
            if ($file !== '.' && $file !== '..') {
                unlink($tempDir .DS . $file);
            }
        }
        rmdir($tempDir);
    }

    /**
     * @throws UploadException
     */
    private function handleFileUploadError($errorCode)
    {
        throw match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => new UploadException('上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值'),
            UPLOAD_ERR_FORM_SIZE => new UploadException('上传的文件超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值'),
            UPLOAD_ERR_PARTIAL => new UploadException('文件只有部分被上传'),
            UPLOAD_ERR_NO_FILE => new UploadException('没有文件被上传'),
            UPLOAD_ERR_NO_TMP_DIR => new UploadException('找不到临时文件夹'),
            UPLOAD_ERR_CANT_WRITE => new UploadException('文件写入失败'),
            UPLOAD_ERR_EXTENSION => new UploadException('由于 PHP 扩展程序的原因，文件上传失败'),
            default => new UploadException('文件上传失败，未知错误代码：' . $errorCode),
        };
    }

    private function isAllowedType($fileName): bool
    {
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        return in_array($fileExt, $this->allowedTypes);
    }

}
