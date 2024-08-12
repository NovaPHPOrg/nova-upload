<?php

namespace nova\plugin\upload;

use nova\plugin\orm\object\Dao;

class FileDao extends Dao
{
    public function useFile($uri_name): void
    {
        $this->update()->set(['is_temp'=>false])->where(['uri_name' => $uri_name])->commit();
    }

    public function removeFile($uri_name): void
    {
        $this->delete()->where(['uri_name' => $uri_name])->commit();
    }

    public function removeTempFiles(): void
    {
        $timeouts = time() - 3600 * 12;// 12 hour
        $this->delete()->where(['is_temp' => true,"create_time < ".$timeouts])->commit();
    }
}