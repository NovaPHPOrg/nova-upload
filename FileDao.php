<?php
declare(strict_types=1);

namespace nova\plugin\upload;

use nova\framework\log\File;
use nova\plugin\orm\object\Dao;

class FileDao extends Dao
{
    public function useFile($uri_name,$link_id): void
    {
        if(!is_array($uri_name)){
            $uri_name = [$uri_name];
        }
        $this->update()->set(['is_temp'=>true])->where(['link_id' => $link_id])->commit();
        foreach ($uri_name as $name){
            $this->update()->set(['is_temp'=>false,'link_id'=>$link_id])->where(['uri_name' => $name])->commit();
        }
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
        $file  = $this->find(null,['uri_name' => $uri_name]);

        if ($file && file_exists($file->path)) {
            unlink($file->path);
        }

        $this->delete()->where(['uri_name' => $uri_name])->commit();
    }


    public function removeFiles($link_id): void
    {
        $files = $this->getAll(null,['link_id' => $link_id]);
        /** @var FileModel $file */
        foreach ($files["data"] as $file) {
            File::del($file->path);
        }
        $this->delete()->where(['link_id' => $link_id])->commit();
    }

    public function removeTempFiles(): void
    {
        $timeouts = time() - 3600 * 12;// 12 hour
        $files = $this->getAll(null,['is_temp' => true,"create_time < ".$timeouts]);
        /** @var FileModel $file */
        foreach ($files["data"] as $file) {
            File::del($file->path);
        }
        $this->delete()->where(['is_temp' => true,"create_time < ".$timeouts])->commit();
    }
}
