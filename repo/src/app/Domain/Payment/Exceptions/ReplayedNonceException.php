<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exceptions;

use RuntimeException;

class ReplayedNonceException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This payment nonce has already been used.');
    }
}
