<?php

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