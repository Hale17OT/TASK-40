<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => Hash::make('9999'), 'role' => 'administrator', 'is_active' => true, 'force_password_change' => false, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

test('GET /menu loads the kiosk-home view', function () {
    $response = $this->get('/menu');
    $response->assertStatus(200);
});

test('POST /logout signs the user out and redirects to /login', function () {
    $response = $this->actingAs(User::find(1))->post('/logout');
    $response->assertRedirect('/login');
    expect(auth()->check())->toBeFalse();
});

test('POST /logout redirects unauthenticated visitors to /login', function () {
    $response = $this->post('/logout');
    $response->assertRedirect('/login');
});

test('GET /admin/security/audit loads for administrator', function () {
    $response = $this->actingAs(User::find(1))->get('/admin/security/audit');
    $response->assertStatus(200);
});

test('GET /admin/security/audit rejects non-administrators', function () {
    DB::table('users')->insert([
        ['id' => 2, 'name' => 'Cashier', 'username' => 'cashier', 'password' => Hash::make('cashier123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => true, 'force_password_change' => false, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $response = $this->actingAs(User::find(2))->get('/admin/security/audit');
    $response->assertStatus(403);
});

test('GET /admin/alerts loads for administrator', function () {
    $response = $this->actingAs(User::find(1))->get('/admin/alerts');
    $response->assertStatus(200);
});

test('GET /admin/alerts rejects non-administrators', function () {
    DB::table('users')->insert([
        ['id' => 3, 'name' => 'Cashier2', 'username' => 'cashier2', 'password' => Hash::make('cashier123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => true, 'force_password_change' => false, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $response = $this->actingAs(User::find(3))->get('/admin/alerts');
    $response->assertStatus(403);
});
