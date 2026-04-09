<?php

declare(strict_types=1);

namespace App\Domain\Auth;

enum UserRole: string
{
    case Cashier = 'cashier';
    case Kitchen = 'kitchen';
    case Manager = 'manager';
    case Administrator = 'administrator';

    public function label(): string
    {
        return match ($this) {
            self::Cashier => 'Cashier',
            self::Kitchen => 'Kitchen',
            self::Manager => 'Manager',
            self::Administrator => 'Administrator',
        };
    }

    public function canAccessStaffRoutes(): bool
    {
        return true; // All roles are staff
    }

    public function canAccessAdminRoutes(): bool
    {
        return $this === self::Administrator;
    }

    public function canAccessManagerRoutes(): bool
    {
        return in_array($this, [self::Manager, self::Administrator]);
    }
}
