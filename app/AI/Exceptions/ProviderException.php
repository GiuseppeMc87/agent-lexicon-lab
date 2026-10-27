<?php

namespace App\AI\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class ProviderException extends RuntimeException implements ShouldntReport
{
    public function __construct(
        public readonly string $failureCode,
        public readonly string $safeMessage,
    ) {
        parent::__construct($safeMessage);
    }
}
