<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => null, 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Cashier', 'username' => 'cashier', 'password' => Hash::make('cashier123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 3, 'name' => 'Kitchen', 'username' => 'kitchen', 'password' => Hash::make('kitchen123'), 'manager_pin' => null, 'role' => 'kitchen', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 4, 'name' => 'Manager', 'username' => 'manager', 'password' => Hash::make('manager123'), 'manager_pin' => Hash::make('1234'), 'role' => 'manager', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    if (DB::getDriverName() === 'pgsql') {
        DB::statement("SELECT setval('users_id_seq', (SELECT MAX(id) FROM users))");
    }
});

// --- Guest access ---

test('guest cannot access staff orders and is redirected to login', function () {
    $this->get('/staff/orders')->assertRedirect('/login');
});

test('guest cannot access admin dashboard and is redirected to login', function () {
    $this->get('/admin/dashboard')->assertRedirect('/login');
});

test('guest cannot access admin menu page', function () {
    $this->get('/admin/menu')->assertRedirect('/login');
});

// --- Cashier access ---

test('cashier can access staff orders', function () {
    $this->actingAs(User::find(2))
        ->get('/staff/orders')
        ->assertStatus(200);
});

test('cashier cannot access admin dashboard', function () {
    $this->actingAs(User::find(2))
        ->get('/admin/dashboard')
        ->assertStatus(403);
});

test('cashier cannot access admin menu', function () {
    $this->actingAs(User::find(2))
        ->get('/admin/menu')
        ->assertStatus(403);
});

test('cashier cannot access admin users', function () {
    $this->actingAs(User::find(2))
        ->get('/admin/users')
        ->assertStatus(403);
});

test('cashier cannot access admin promotions', function () {
    $this->actingAs(User::find(2))
        ->get('/admin/promotions')
        ->assertStatus(403);
});

// --- Kitchen access ---

test('kitchen can access staff orders', function () {
    $this->actingAs(User::find(3))
        ->get('/staff/orders')
        ->assertStatus(200);
});

test('kitchen cannot access admin dashboard', function () {
    $this->actingAs(User::find(3))
        ->get('/admin/dashboard')
        ->assertStatus(403);
});

test('kitchen cannot access admin routes', function () {
    $kitchen = User::find(3);
    $this->actingAs($kitchen)->get('/admin/menu')->assertStatus(403);
    $this->actingAs($kitchen)->get('/admin/users')->assertStatus(403);
    $this->actingAs($kitchen)->get('/admin/promotions')->assertStatus(403);
    $this->actingAs($kitchen)->get('/admin/security')->assertStatus(403);
});

// --- Manager access ---

test('manager can access staff orders', function () {
    $this->actingAs(User::find(4))
        ->get('/staff/orders')
        ->assertStatus(200);
});

test('manager cannot access admin dashboard', function () {
    $this->actingAs(User::find(4))
        ->get('/admin/dashboard')
        ->assertStatus(403);
});

test('manager can access reconciliation', function () {
    $this->actingAs(User::find(4))
        ->get('/manager/reconciliation')
        ->assertStatus(200);
});

// --- Admin access ---

test('admin can access admin dashboard', function () {
    $this->actingAs(User::find(1))
        ->get('/admin/dashboard')
        ->assertStatus(200);
});

test('admin can access admin menu', function () {
    $this->actingAs(User::find(1))
        ->get('/admin/menu')
        ->assertStatus(200);
});

test('admin can access admin users', function () {
    $this->actingAs(User::find(1))
        ->get('/admin/users')
        ->assertStatus(200);
});

test('admin can access admin promotions', function () {
    $this->actingAs(User::find(1))
        ->get('/admin/promotions')
        ->assertStatus(200);
});

test('admin can access staff orders', function () {
    $this->actingAs(User::find(1))
        ->get('/staff/orders')
        ->assertStatus(200);
});

test('admin can access reconciliation', function () {
    $this->actingAs(User::find(1))
        ->get('/manager/reconciliation')
        ->assertStatus(200);
});
