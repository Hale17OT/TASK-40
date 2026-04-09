<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Only seed if users table is empty
        if (DB::table('users')->count() > 0) {
            return;
        }

        // WARNING: These are non-production seed credentials for initial setup only.
        // All seeded accounts are flagged with force_password_change=true.
        // Users MUST change their password and PIN on first login.
        $users = [
            [
                'name' => 'System Administrator',
                'username' => 'admin',
                'password' => Hash::make('admin123'),
                'manager_pin' => Hash::make('9999'),
                'role' => 'administrator',
                'is_active' => true,
                'force_password_change' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Floor Manager',
                'username' => 'manager',
                'password' => Hash::make('manager123'),
                'manager_pin' => Hash::make('1234'),
                'role' => 'manager',
                'is_active' => true,
                'force_password_change' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Front Cashier',
                'username' => 'cashier',
                'password' => Hash::make('cashier123'),
                'manager_pin' => null,
                'role' => 'cashier',
                'is_active' => true,
                'force_password_change' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Kitchen Staff',
                'username' => 'kitchen',
                'password' => Hash::make('kitchen123'),
                'manager_pin' => null,
                'role' => 'kitchen',
                'is_active' => true,
                'force_password_change' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('users')->insert($users);
    }
}
