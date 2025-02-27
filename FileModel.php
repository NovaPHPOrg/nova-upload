<?php
/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

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