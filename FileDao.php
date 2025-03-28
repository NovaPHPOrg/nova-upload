<?php

declare(strict_types=1);

namespace nova\plugin\upload;

use nova\framework\core\File;
use nova\framework\core\Logger;
use nova\plugin\orm\object\Dao;

class FileDao extends Dao
{
    public function useFile($uri_name, $link_id): void
    {
        if (!is_array($uri_name)) {
            $uri_name = [$uri_name];
        }
        $this->update()->set(['is_temp' => true])->where(['link_id' => $link_id])->commit();
        foreach ($uri_name as $name) {
            if (empty($name)) {
                continue;
            }
            $this->update()->set(['is_temp' => false,'link_id' => $link_id])->where(['uri_name' => $name])->commit();
        }
    }

    public function getFile($uri_name): ?FileModel
    {
        return $this->find(null, ['uri_name' => $uri_name]);
    }

    public function getFileByPath($path): ?FileModel
    {
        return $this->find(null, ['path' => $path]);
    }

    private function extraImage($content, $pattern): array
    {
        $filenames = [];
        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[1] as $src) {
                $filename = basename(parse_url($src, PHP_URL_PATH));
                $filenames[] = $filename;
            }
        }
        return $filenames;
    }

    public function useMarkdownFile($markdown, $link_id): void
    {
        $pattern = '/!\[.*?]\((.*?)\)/';
        $filenames = $this->extraImage($markdown, $pattern);
        $this->useFile($filenames, $link_id);
    }

    public function useHtmlFile($html, $link_id): void
    {
        $pattern = '/<img[^>]+src=["\']([^"\']+)["\']/i';
        $filenames = $this->extraImage($html, $pattern);
        $this->useFile($filenames, $link_id);
    }

    public function removeFile($uri_name): void
    {

        if (empty($uri_name)) {
            return;
        }
        /**
         * @var FileModel $file
         */
        $file  = $this->find(null, ['uri_name' => $uri_name]);

        if (empty($file->link_id)) {
            return;
        }

        if ($file && file_exists($file->path)) {
            //Logger::info("删除文件(removeFile)".print_r($file,true).":".$file->path);
            File::del($file->path);
        }
        $this->delete()->where(['id' => $file->id])->commit();
        Logger::info(print_r(debug_backtrace(), true));
    }

    public function removeFiles($link_id): void
    {
        if (empty($link_id)) {
            return;
        }
        $files = $this->getAll(null, ['link_id' => $link_id]);
        /** @var FileModel $file */
        foreach ($files["data"] as $file) {
            if (empty($file->uri_name)) {
                continue;
            }
            File::del($file->path);
            //    Logger::info("删除文件(removeFiles)".print_r($file,true).":".$file->path);
        }
        $this->delete()->where(['link_id' => $link_id])->commit();
        // Logger::info(print_r(debug_backtrace(),true));
    }

    public function removeTempFiles(): void
    {
        $timeouts = time() - 3600 * 12;//- 3600 * 12;// 12 hour
        $files = $this->getAll(null, ['is_temp' => true,"create_time < ".$timeouts]);
        /** @var FileModel $file */
        foreach ($files["data"] as $file) {
            File::del($file->path);
            //    Logger::info("删除临时文件(removeTempFiles)".print_r($file,true).":".$file->path);
        }
        //  Logger::info(print_r(debug_backtrace(),true));
        $this->delete()->where(['is_temp' => true, "create_time < " . $timeouts])->commit();
    }

    public function getAbsolutePath($name): ?string
    {
        $name = preg_replace('/^(\d{4})(\d{2})(\d{2})-/', '$1/$2/$3/', $name);
        $file = ROOT_PATH . '/uploads/' . $name;
        return $file;
    }
}
