<?php

declare(strict_types=1);

namespace nova\plugin\upload;

use nova\plugin\orm\object\Model;

class FileModel extends Model
{
    public string $name = '';
    public int $size = 0;
    public string $path = '';
    public string $extension = '';
    public string $uri_name = '';

    public bool $is_temp = false;

    public int $create_time = 0;

    public string $link_id = "";// 关联id
}
