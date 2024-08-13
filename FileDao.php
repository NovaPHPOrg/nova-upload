<?php

namespace nova\plugin\upload;

use nova\plugin\orm\object\Dao;

class FileDao extends Dao
{
    public function useFile($uri_name,$link_id): void
    {
        $this->update()->set(['is_temp'=>true])->where(['link_id' => $link_id])->commit();
        $this->update()->set(['is_temp'=>false,'link_id'=>$link_id])->where(['uri_name' => $uri_name])->commit();
    }


    public function removeFile($uri_name): void
    {
        $file  = $this->find(null,['uri_name' => $uri_name]);

        if ($file && file_exists($file->path)) {
            unlink($file->path);
        }

        $this->delete()->where(['uri_name' => $uri_name])->commit();
    }

    public function removeTempFiles(): void
    {
        $timeouts = time() - 3600 * 12;// 12 hour
        $files = $this->getAll(null,['is_temp' => true,"create_time < ".$timeouts]);
        /** @var FileModel $file */
        foreach ($files as $file) {
            if (file_exists($file->path)) unlink($file->path);
        }
        $this->delete()->where(['is_temp' => true,"create_time < ".$timeouts])->commit();
    }
}