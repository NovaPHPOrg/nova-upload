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

use Exception;
use nova\framework\log\Logger;
use Throwable;

class UploadException extends Exception
{
    public function __construct($message, $code = 0, Throwable $previous = null)
    {
        Logger::error("UploadException: $message");
        parent::__construct($message, $code, $previous);
    }
}
