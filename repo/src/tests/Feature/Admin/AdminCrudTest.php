<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => null, 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Cashier', 'username' => 'cashier', 'password' => Hash::make('cashier123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // Reset PG sequences to avoid conflicts with explicit IDs above
    if (DB::getDriverName() === 'pgsql') {
        DB::statement("SELECT setval('users_id_seq', (SELECT MAX(id) FROM users))");
        DB::statement("SELECT setval('menu_categories_id_seq', (SELECT MAX(id) FROM menu_categories))");
    }
});

// --- Menu Item CRUD ---

test('admin can create a menu item via MenuManager', function () {
    \Livewire\Livewire::actingAs(User::find(1))
        ->test(\App\Livewire\Admin\MenuManager::class)
        ->set('itemSku', 'NEW-001')
        ->set('itemName', 'New Burger')
        ->set('itemDescription', 'A fresh burger')
        ->set('itemPrice', '9.99')
        ->set('itemTaxCategory', 'hot_prepared')
        ->set('itemIsActive', true)
        ->set('itemCategoryId', 1)
        ->call('saveItem')
        ->assertSet('message', 'Menu item created.')
        ->assertSet('error', null);

    $item = DB::table('menu_items')->where('sku', 'NEW-001')->first();
    expect($item)->not->toBeNull();
    expect($item->name)->toBe('New Burger');
    expect((float) $item->price)->toBe(9.99);
});

test('admin can create a menu category via MenuManager', function () {
    \Livewire\Livewire::actingAs(User::find(1))
        ->test(\App\Livewire\Admin\MenuManager::class)
        ->set('catName', 'Drinks')
        ->set('catSortOrder', 2)
        ->set('catIsActive', true)
        ->call('saveCategory')
        ->assertSet('message', 'Category created.')
        ->assertSet('error', null);

    $cat = DB::table('menu_categories')->where('name', 'Drinks')->first();
    expect($cat)->not->toBeNull();
});

test('menu item requires sku and name', function () {
    \Livewire\Livewire::actingAs(User::find(1))
        ->test(\App\Livewire\Admin\MenuManager::class)
        ->set('itemSku', '')
        ->set('itemName', '')
        ->set('itemCategoryId', 1)
        ->set('itemPrice', '5.00')
        ->call('saveItem')
        ->assertSet('error', 'SKU and Name are required.');
});

test('duplicate sku is rejected', function () {
    DB::table('menu_items')->insert([
        'id' => 1, 'sku' => 'EXIST-001', 'menu_category_id' => 1, 'name' => 'Existing',
        'price' => 10.00, 'tax_category' => 'hot_prepared', 'is_active' => true,
        'attributes' => '{}', 'created_at' => now(), 'updated_at' => now(),
    ]);

    \Livewire\Livewire::actingAs(User::find(1))
        ->test(\App\Livewire\Admin\MenuManager::class)
        ->set('itemSku', 'EXIST-001')
        ->set('itemName', 'Duplicate')
        ->set('itemCategoryId', 1)
        ->set('itemPrice', '5.00')
        ->call('saveItem')
        ->assertSet('error', 'SKU already exists.');
});

// --- Promotion CRUD ---

test('admin can create a promotion via PromotionManager', function () {
    $startsAt = now()->format('Y-m-d\TH:i');
    $endsAt = now()->addDays(7)->format('Y-m-d\TH:i');

    \Livewire\Livewire::actingAs(User::find(1))
        ->test(\App\Livewire\Admin\PromotionManager::class)
        ->set('name', '10% Off')
        ->set('type', 'percentage_off')
        ->set('rulesJson', '{"percentage": 10}')
        ->set('startsAt', $startsAt)
        ->set('endsAt', $endsAt)
        ->set('isActive', true)
        ->call('save')
        ->assertSet('message', 'Promotion created.')
        ->assertSet('error', null);

    $promo = DB::table('promotions')->where('name', '10% Off')->first();
    expect($promo)->not->toBeNull();
    expect($promo->type)->toBe('percentage_off');
});

test('promotion requires valid json rules', function () {
    $startsAt = now()->format('Y-m-d\TH:i');
    $endsAt = now()->addDays(7)->format('Y-m-d\TH:i');

    \Livewire\Livewire::actingAs(User::find(1))
        ->test(\App\Livewire\Admin\PromotionManager::class)
        ->set('name', 'Bad Promo')
        ->set('type', 'percentage_off')
        ->set('rulesJson', '{invalid json}')
        ->set('startsAt', $startsAt)
        ->set('endsAt', $endsAt)
        ->call('save')
        ->assertSet('error', 'Rules must be valid JSON. Example: {"threshold": 30, "percentage": 10}');
});

test('promotion end date must be after start date', function () {
    $time = now()->format('Y-m-d\TH:i');

    \Livewire\Livewire::actingAs(User::find(1))
        ->test(\App\Livewire\Admin\PromotionManager::class)
        ->set('name', 'Bad Dates')
        ->set('type', 'percentage_off')
        ->set('rulesJson', '{}')
        ->set('startsAt', $time)
        ->set('endsAt', $time)
        ->call('save')
        ->assertSet('error', 'End date must be after start date.');
});

// --- User CRUD ---

test('admin can create a user via UserManager', function () {
    \Livewire\Livewire::actingAs(User::find(1))
        ->test(\App\Livewire\Admin\UserManager::class)
        ->set('name', 'New Staff')
        ->set('username', 'newstaff')
        ->set('password', 'securepassword')
        ->set('role', 'cashier')
        ->set('isActive', true)
        ->call('saveUser')
        ->assertSet('message', 'User created.')
        ->assertSet('error', null);

    $user = DB::table('users')->where('username', 'newstaff')->first();
    expect($user)->not->toBeNull();
    expect($user->role)->toBe('cashier');
    expect($user->name)->toBe('New Staff');
});

test('user creation requires password', function () {
    \Livewire\Livewire::actingAs(User::find(1))
        ->test(\App\Livewire\Admin\UserManager::class)
        ->set('name', 'No Pass')
        ->set('username', 'nopass')
        ->set('password', '')
        ->set('role', 'cashier')
        ->call('saveUser')
        ->assertSet('error', 'Password is required for new users.');
});

test('duplicate username is rejected', function () {
    \Livewire\Livewire::actingAs(User::find(1))
        ->test(\App\Livewire\Admin\UserManager::class)
        ->set('name', 'Duplicate')
        ->set('username', 'admin')
        ->set('password', 'password123')
        ->set('role', 'cashier')
        ->call('saveUser')
        ->assertSet('error', 'Username already taken.');
});

// --- Non-admin cannot access admin Livewire components ---

test('non-admin cannot access admin menu page to use MenuManager', function () {
    $this->actingAs(User::find(2))
        ->get('/admin/menu')
        ->assertStatus(403);
});

test('non-admin cannot access admin users page to use UserManager', function () {
    $this->actingAs(User::find(2))
        ->get('/admin/users')
        ->assertStatus(403);
});

test('non-admin cannot access admin promotions page to use PromotionManager', function () {
    $this->actingAs(User::find(2))
        ->get('/admin/promotions')
        ->assertStatus(403);
});
