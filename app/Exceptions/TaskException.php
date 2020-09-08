<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class TaskException extends Exception
{
    private $trace;

    public function __construct($message, $trace, $code = 0, Exception $previous = null)
    {
        $this->trace = $trace;

        parent::__construct($message, $code, $previous);
    }
}
