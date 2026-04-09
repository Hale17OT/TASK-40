<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'password',
        'manager_pin',
        'role',
        'is_active',
        'force_password_change',
    ];

    protected $hidden = [
        'password',
        'manager_pin',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'force_password_change' => 'boolean',
        ];
    }

    public function isRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isManager(): bool
    {
        return $this->isRole('manager', 'administrator');
    }

    public function isAdmin(): bool
    {
        return $this->isRole('administrator');
    }
}
