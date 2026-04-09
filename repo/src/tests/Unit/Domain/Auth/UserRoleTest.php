<?php

use App\Domain\Auth\UserRole;

test('all four roles are defined', function () {
    expect(UserRole::cases())->toHaveCount(4);
    expect(UserRole::Cashier->value)->toBe('cashier');
    expect(UserRole::Kitchen->value)->toBe('kitchen');
    expect(UserRole::Manager->value)->toBe('manager');
    expect(UserRole::Administrator->value)->toBe('administrator');
});

test('labels return human-readable names', function () {
    expect(UserRole::Cashier->label())->toBe('Cashier');
    expect(UserRole::Kitchen->label())->toBe('Kitchen');
    expect(UserRole::Manager->label())->toBe('Manager');
    expect(UserRole::Administrator->label())->toBe('Administrator');
});

test('all roles can access staff routes', function () {
    foreach (UserRole::cases() as $role) {
        expect($role->canAccessStaffRoutes())->toBeTrue();
    }
});

test('only administrator can access admin routes', function () {
    expect(UserRole::Administrator->canAccessAdminRoutes())->toBeTrue();
    expect(UserRole::Manager->canAccessAdminRoutes())->toBeFalse();
    expect(UserRole::Cashier->canAccessAdminRoutes())->toBeFalse();
    expect(UserRole::Kitchen->canAccessAdminRoutes())->toBeFalse();
});

test('manager and administrator can access manager routes', function () {
    expect(UserRole::Manager->canAccessManagerRoutes())->toBeTrue();
    expect(UserRole::Administrator->canAccessManagerRoutes())->toBeTrue();
    expect(UserRole::Cashier->canAccessManagerRoutes())->toBeFalse();
    expect(UserRole::Kitchen->canAccessManagerRoutes())->toBeFalse();
});
