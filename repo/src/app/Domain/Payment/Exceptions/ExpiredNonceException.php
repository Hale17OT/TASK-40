<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exceptions;

use RuntimeException;

class ExpiredNonceException extends RuntimeException
{
    public function __construct(int $expirySeconds = 300)
    {
        parent::__construct("Payment intent expired. Intents are valid for {$expirySeconds} seconds.");
    }
}
