<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => Hash::make('9999'), 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    if (DB::getDriverName() === 'pgsql') {
        DB::statement("SELECT setval('users_id_seq', (SELECT MAX(id) FROM users))");
    }
});

test('creating a user with manager PIN hashes the PIN', function () {
    \Livewire\Livewire::test(\App\Livewire\Admin\UserManager::class)
        ->set('name', 'New Manager')
        ->set('username', 'newmanager')
        ->set('password', 'test123')
        ->set('role', 'manager')
        ->set('managerPin', '5678')
        ->call('saveUser');

    $user = DB::table('users')->where('username', 'newmanager')->first();

    expect($user)->not->toBeNull();
    // PIN should be hashed (bcrypt hashes start with $2y$)
    expect($user->manager_pin)->not->toBe('5678');
    expect(str_starts_with($user->manager_pin, '$2y$'))->toBeTrue();
    // And verification should work
    expect(password_verify('5678', $user->manager_pin))->toBeTrue();
});

test('editing a user without changing PIN preserves existing PIN', function () {
    // Create a user with PIN
    DB::table('users')->insert([
        'id' => 2, 'name' => 'Manager', 'username' => 'mgr', 'password' => Hash::make('test123'),
        'manager_pin' => Hash::make('1234'), 'role' => 'manager', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $originalPin = DB::table('users')->where('id', 2)->value('manager_pin');

    \Livewire\Livewire::test(\App\Livewire\Admin\UserManager::class)
        ->call('editUser', 2)
        ->set('name', 'Updated Manager')
        ->set('managerPin', '') // Empty = keep existing
        ->call('saveUser');

    $updatedPin = DB::table('users')->where('id', 2)->value('manager_pin');
    expect($updatedPin)->toBe($originalPin);
});

test('edit form does not expose PIN hash', function () {
    DB::table('users')->insert([
        'id' => 2, 'name' => 'Manager', 'username' => 'mgr', 'password' => Hash::make('test123'),
        'manager_pin' => Hash::make('1234'), 'role' => 'manager', 'is_active' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $component = \Livewire\Livewire::test(\App\Livewire\Admin\UserManager::class)
        ->call('editUser', 2);

    // managerPin should be empty, never the hash
    expect($component->get('managerPin'))->toBe('');
});

test('role taxonomy uses kitchen not kitchen_staff', function () {
    $component = \Livewire\Livewire::test(\App\Livewire\Admin\UserManager::class);

    expect($component->get('availableRoles'))->toContain('kitchen');
    expect($component->get('availableRoles'))->not->toContain('kitchen_staff');
});

test('step-up verification works with hashed PIN', function () {
    $hashedPin = Hash::make('9999');
    $verifier = new \App\Domain\Auth\StepUpVerifier();

    expect($verifier->verify('9999', $hashedPin))->toBeTrue();
    expect($verifier->verify('wrong', $hashedPin))->toBeFalse();
});
