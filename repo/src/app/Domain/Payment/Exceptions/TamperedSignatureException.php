<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exceptions;

use RuntimeException;

class TamperedSignatureException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Signature verification failed. The payment parameters may have been tampered with.');
    }
}
