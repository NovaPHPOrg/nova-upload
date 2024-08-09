<?php
namespace nova\plugin\upload;

class FileUploader
{
    private string $uploadDir;
    private array $allowedTypes;
    private int $maxFileSize;

    public function __construct($uploadDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff'], $maxFileSize = 2097152)
    {
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        $this->allowedTypes = $allowedTypes;
        $this->maxFileSize = $maxFileSize;

        // 创建日期目录
        $dateDir = date('Y/m/d');
        $this->uploadDir .= $dateDir . '/';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    /**
     * @throws UploadException
     */
    public function upload($file, $chunkIndex = 0, $totalChunks = 1): string
    {
        // 检查是否有上传错误
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new UploadException($this->handleError($file['error']));
        }

        // 检查文件类型
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if (!in_array(strtolower($fileExtension), $this->allowedTypes)) {
            throw new UploadException("不支持的文件类型。");
        }

        // 检查文件大小（单个切片的大小）
        if ($file['size'] > $this->maxFileSize) {
            throw new UploadException("文件切片太大。最大允许大小是 " . ($this->maxFileSize / 1024 / 1024) . " MB.");
        }

        // 生成唯一文件名
        $fileName = $this->generateUniqueFileName($file['name']);
        $filePath = $this->uploadDir . $fileName;

        // 处理切片上传
        $chunkFilePath = $filePath . ".part{$chunkIndex}";
        if (move_uploaded_file($file['tmp_name'], $chunkFilePath)) {
            if ($chunkIndex == $totalChunks - 1) {
                // 如果是最后一个切片，合并所有切片
                $this->mergeChunks($filePath, $totalChunks);
                return "文件上传成功: " . $fileName;
            } else {
                return "切片 {$chunkIndex} 上传成功。";
            }
        } else {
            throw new UploadException("上传文件保存失败。");
        }
    }

    private function mergeChunks($filePath, $totalChunks): void
    {
        $outFile = fopen($filePath, 'w');
        for ($i = 0; $i < $totalChunks; $i++) {
            $chunkFilePath = $filePath . ".part{$i}";
            $inFile = fopen($chunkFilePath, 'r');
            while ($buffer = fread($inFile, 4096)) {
                fwrite($outFile, $buffer);
            }
            fclose($inFile);
            unlink($chunkFilePath); // 删除切片文件
        }
        fclose($outFile);
    }

    private function handleError($errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "文件太大。",
            UPLOAD_ERR_PARTIAL => "文件只有部分上传。",
            UPLOAD_ERR_NO_FILE => "没有上传文件。",
            UPLOAD_ERR_NO_TMP_DIR => "缺少临时文件夹。",
            UPLOAD_ERR_CANT_WRITE => "无法写入文件到磁盘。",
            UPLOAD_ERR_EXTENSION => "一个PHP扩展停止了文件上传。",
            default => "未知的上传错误。",
        };
    }

    private function generateUniqueFileName($originalName): string
    {
        $fileInfo = pathinfo($originalName);
        $fileName = $fileInfo['filename'];
        $extension = $fileInfo['extension'] ?? '';

        return $fileName . '_' . uniqid() . ($extension ? '.' . $extension : '');
    }
}
