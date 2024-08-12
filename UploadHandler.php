<?php
namespace nova\plugin\upload;

use nova\framework\log\Logger;
use nova\framework\request\Argument;

class UploadHandler {
    private string $uploadDir;
    private array $allowedTypes;
    private int $maxFileSize;

    public function __construct($uploadDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff'], $maxFileSize = 0) {
        $this->uploadDir = rtrim($uploadDir, DS) . DS;
        $this->allowedTypes = $allowedTypes;
        $this->maxFileSize = $maxFileSize;

        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
        $this->cleanUpUseLessTempDir();
    }

    /**
     * @throws UploadException
     */
    public function handleUpload(): ?FileModel
    {
        $chunkIndex = Argument::post('chunkIndex', 0);
        $totalChunks = Argument::post('totalChunks', 1);
        $fileName = Argument::post('fileName', '');

        Logger::info('chunkIndex: ' . $chunkIndex);
        Logger::info('totalChunks: ' . $totalChunks);
        Logger::info('fileName: ' . $fileName);

        // Check if file was uploaded without errors
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->handleFileUploadError($_FILES['file']['error']);
        }

        // Validate file type
        if (!$this->isAllowedType($fileName)) {
            throw new UploadException('不允许的文件类型');
        }

        // Check file size
        if ($this->maxFileSize > 0 && $_FILES['file']['size'] > $this->maxFileSize) {
            throw new UploadException('文件过大');
        }

        $fileExt = pathinfo($fileName, PATHINFO_EXTENSION);
        $uniqueFileName = uniqid();
        $uniqueFileNameExt  = $uniqueFileName.".". $fileExt;


        // Directory for storing temporary chunks
        $tempDir = $this->uploadDir . 'temp'.DS . $uniqueFileName.DS  ;
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // Store the current chunk
        $chunkPath = $tempDir . $chunkIndex;
        move_uploaded_file($_FILES['file']['tmp_name'], $chunkPath);

        // Check if all chunks are uploaded
        if ($chunkIndex + 1 === $totalChunks) {
            Logger::info('All chunks uploaded');

            // Merge chunks into a single file
            $finalFilePath = $this->mergeChunks($tempDir, $uniqueFileNameExt,$totalChunks);
            Logger::info('Final file path: ' . $finalFilePath);
            // Get current date for directory structure
            $year = date('Y');
            $month = date('m');
            $day = date('d');

            // Final storage directory
            $dateDir = $this->uploadDir . $year . '/' . $month . '/' . $day . '/';
            if (!file_exists($dateDir)) {
                mkdir($dateDir, 0777, true);
            }

            // Move the merged file to the final directory
            $finalPath = $dateDir . $uniqueFileNameExt;
            rename($finalFilePath, $finalPath);

            // Create file model to return
            $fileModel = new FileModel();
            $fileModel->name = $fileName;
            $fileModel->size = filesize($finalPath);
            $fileModel->path = $finalPath;
            $fileModel->extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $fileModel->uri_name = $year . $month . $day . "-" . $uniqueFileNameExt;
            $fileModel->is_temp = true;
            $fileModel->create_time = time();
            // Clean up temporary files
            $this->cleanUpTempDir($tempDir);

            return $fileModel;
        } else {
            return null; // Indicate that more chunks are needed
        }
    }

    /**
     * Merges all chunks into a single file.
     * @throws UploadException
     */
    private function mergeChunks(string $tempDir, string $finalFileName,int $total): string
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
                throw new UploadException('缺少文件块，无法完成上传');
            }
        }

        fclose($outputFile);
        return $finalFilePath;
    }
    public function cleanUpUseLessTempDir(): void
    {
        $tempDir = $this->uploadDir . 'temp'.DS;
        if(!is_dir($tempDir)){
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
    private function handleFileUploadError($errorCode) {
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