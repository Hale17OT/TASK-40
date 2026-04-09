<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => null, 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Cashier', 'username' => 'cashier', 'password' => Hash::make('cashier123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'name' => 'Inactive', 'username' => 'inactive', 'password' => Hash::make('inactive123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => false, 'created_at' => now(), 'updated_at' => now()],
    ]);

    if (DB::getDriverName() === 'pgsql') {
        DB::statement("SELECT setval('users_id_seq', (SELECT MAX(id) FROM users))");
    }
});

test('login page loads', function () {
    $this->get('/login')->assertStatus(200);
});

test('valid admin login redirects to admin dashboard', function () {
    \Livewire\Livewire::test(\App\Livewire\Auth\LoginForm::class)
        ->set('username', 'admin')
        ->set('password', 'admin123')
        ->call('login')
        ->assertRedirect('/admin/dashboard');
});

test('valid cashier login redirects to staff orders', function () {
    \Livewire\Livewire::test(\App\Livewire\Auth\LoginForm::class)
        ->set('username', 'cashier')
        ->set('password', 'cashier123')
        ->call('login')
        ->assertRedirect('/staff/orders');
});

test('invalid credentials return error', function () {
    \Livewire\Livewire::test(\App\Livewire\Auth\LoginForm::class)
        ->set('username', 'cashier')
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('username')
        ->assertNoRedirect();
});

test('inactive user cannot login', function () {
    \Livewire\Livewire::test(\App\Livewire\Auth\LoginForm::class)
        ->set('username', 'inactive')
        ->set('password', 'inactive123')
        ->call('login')
        ->assertHasErrors('username')
        ->assertNoRedirect();
});

test('blacklisted username cannot login', function () {
    DB::table('security_blacklists')->insert([
        'type' => 'username',
        'value' => 'cashier',
        'reason' => 'Suspicious activity',
        'expires_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    \Livewire\Livewire::test(\App\Livewire\Auth\LoginForm::class)
        ->set('username', 'cashier')
        ->set('password', 'cashier123')
        ->call('login')
        ->assertHasErrors('username')
        ->assertNoRedirect();
});

test('expired blacklist does not prevent login', function () {
    DB::table('security_blacklists')->insert([
        'type' => 'username',
        'value' => 'cashier',
        'reason' => 'Expired ban',
        'expires_at' => now()->subDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    \Livewire\Livewire::test(\App\Livewire\Auth\LoginForm::class)
        ->set('username', 'cashier')
        ->set('password', 'cashier123')
        ->call('login')
        ->assertRedirect('/staff/orders');
});
