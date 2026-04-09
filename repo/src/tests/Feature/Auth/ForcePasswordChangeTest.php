<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        [
            'id' => 1,
            'name' => 'Admin',
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
            'id' => 2,
            'name' => 'Cashier',
            'username' => 'cashier',
            'password' => Hash::make('cashier123'),
            'manager_pin' => null,
            'role' => 'cashier',
            'is_active' => true,
            'force_password_change' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);
});

test('user with force_password_change is redirected to password change page', function () {
    $this->actingAs(User::find(1))
        ->get('/admin/dashboard')
        ->assertRedirect(route('password.force-change'));
});

test('user without force_password_change can access normal routes', function () {
    $this->actingAs(User::find(2))
        ->get('/staff/orders')
        ->assertStatus(200);
});

test('API returns 403 with FORCE_PASSWORD_CHANGE for flagged user', function () {
    $this->actingAs(User::find(1))
        ->postJson('/api/orders/1/transition', [
            'target_status' => 'in_preparation',
            'expected_version' => 1,
        ])
        ->assertStatus(403)
        ->assertJson(['error_code' => 'FORCE_PASSWORD_CHANGE']);
});

test('password change route is accessible for flagged user', function () {
    $this->actingAs(User::find(1))
        ->get(route('password.force-change'))
        ->assertStatus(200);
});
