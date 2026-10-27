<?php

namespace App\AI\Exceptions;

use RuntimeException;

class ExecutionDisabledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Paid AI execution is disabled by configuration.');
    }
}
