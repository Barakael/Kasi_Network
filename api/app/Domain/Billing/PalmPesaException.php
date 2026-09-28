<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use RuntimeException;
use Throwable;

final class PalmPesaException extends RuntimeException
{
    public function __construct(string $message, int $httpStatus = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $httpStatus, $previous);
    }
}
