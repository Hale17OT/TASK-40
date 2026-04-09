<?php

declare(strict_types=1);

namespace App\Application\Exceptions;

/**
 * Business logic exception for expected user/client error conditions.
 * Mapped to 4xx HTTP responses (not 500).
 */
class BusinessException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'BUSINESS_ERROR',
        public readonly int $httpStatus = 422,
    ) {
        parent::__construct($message);
    }
}
