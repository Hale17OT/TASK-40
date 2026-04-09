<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

use RuntimeException;

class InsufficientRoleException extends RuntimeException
{
    public function __construct(
        public readonly string $role,
        public readonly string $action,
        public readonly array $requiredRoles = [],
        public readonly bool $requiresPin = false,
    ) {
        $msg = $requiresPin
            ? "Manager PIN required to {$action}"
            : "Role '{$role}' is not authorized to {$action}. Required: " . implode(', ', $requiredRoles);
        parent::__construct($msg);
    }
}
