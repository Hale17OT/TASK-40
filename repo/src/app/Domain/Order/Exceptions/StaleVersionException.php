<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

use RuntimeException;

class StaleVersionException extends RuntimeException
{
    public function __construct(
        public readonly int $currentVersion,
        public readonly int $expectedVersion,
        public readonly string $currentStatus,
    ) {
        parent::__construct(
            "Version conflict: expected version {$expectedVersion} but current is {$currentVersion}"
        );
    }
}
