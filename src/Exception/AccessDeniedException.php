<?php

namespace App\Exception;

use Exception;
use Throwable;

class AccessDeniedException extends Exception
{
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct(...func_get_args());
    }
}