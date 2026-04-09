<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    DB::table('users')->insert([
        ['id' => 1, 'name' => 'Admin', 'username' => 'admin', 'password' => Hash::make('admin123'), 'manager_pin' => Hash::make('9999'), 'role' => 'administrator', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 2, 'name' => 'Cashier', 'username' => 'cashier', 'password' => Hash::make('cashier123'), 'manager_pin' => null, 'role' => 'cashier', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ['id' => 4, 'name' => 'Manager', 'username' => 'manager', 'password' => Hash::make('manager123'), 'manager_pin' => Hash::make('1234'), 'role' => 'manager', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    DB::table('menu_categories')->insert([
        ['id' => 1, 'name' => 'Burgers', 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('menu_items')->insert([
        ['id' => 1, 'sku' => 'T-001', 'menu_category_id' => 1, 'name' => 'Test Burger', 'description' => 'Test', 'price' => 12.99, 'tax_category' => 'hot_prepared', 'is_active' => true, 'attributes' => '{}', 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('tax_rules')->insert([
        ['category' => 'hot_prepared', 'rate' => 0.0825, 'effective_from' => '2026-01-01', 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);
    DB::table('carts')->insert(['id' => 1, 'session_id' => 'test-session', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cart_items')->insert([
        ['cart_id' => 1, 'menu_item_id' => 1, 'quantity' => 2, 'unit_price_snapshot' => 12.99, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

function createServedOrder(): array
{
    $order = (new \App\Application\Order\CreateOrderUseCase())->execute(1);
    $orderId = (int) $order['id'];
    $transition = app(\App\Application\Order\TransitionOrderUseCase::class);
    $transition->execute($orderId, 'in_preparation', 1, 'cashier', 2);
    $transition->execute($orderId, 'served', 2, 'kitchen', 2);
    return (array) DB::table('orders')->find($orderId);
}

test('normal settlement succeeds without manager PIN', function () {
    $order = createServedOrder();
    $orderId = (int) $order['id'];

    $createIntent = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $createIntent->execute($orderId);

    $confirm = app(\App\Application\Payment\ConfirmPaymentUseCase::class);
    $result = $confirm->execute(
        reference: $intent['reference'],
        hmacSignature: $intent['hmac_signature'],
        nonce: $intent['nonce'],
        method: 'cash',
        confirmedBy: 2,
        actorRole: 'cashier',
        expectedVersion: (int) $order['version'],
    );

    expect($result['order_status'])->toBe('settled');
    expect($result['idempotent'])->toBeFalse();
});

test('ambiguous settlement rejects cashier role (manager/admin required)', function () {
    $order = createServedOrder();
    $orderId = (int) $order['id'];

    // Create intent first (HMAC signs the current order total), then modify
    // the order total to create the amount mismatch without invalidating HMAC
    $createIntent = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $createIntent->execute($orderId);

    DB::table('orders')->where('id', $orderId)->update(['total' => 999.99]);

    $confirm = app(\App\Application\Payment\ConfirmPaymentUseCase::class);

    // Cashier is rejected at the role gate before PIN check
    expect(fn () => $confirm->execute(
        reference: $intent['reference'],
        hmacSignature: $intent['hmac_signature'],
        nonce: $intent['nonce'],
        method: 'cash',
        confirmedBy: 2,
        actorRole: 'cashier',
        expectedVersion: (int) $order['version'],
    ))->toThrow(\App\Application\Exceptions\BusinessException::class, 'manager or administrator role');
});

test('ambiguous settlement fails for manager without PIN', function () {
    $order = createServedOrder();
    $orderId = (int) $order['id'];

    $createIntent = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $createIntent->execute($orderId);

    DB::table('orders')->where('id', $orderId)->update(['total' => 999.99]);

    $confirm = app(\App\Application\Payment\ConfirmPaymentUseCase::class);

    // Manager passes role gate but fails without PIN
    expect(fn () => $confirm->execute(
        reference: $intent['reference'],
        hmacSignature: $intent['hmac_signature'],
        nonce: $intent['nonce'],
        method: 'cash',
        confirmedBy: 4,
        actorRole: 'manager',
        expectedVersion: (int) $order['version'],
    ))->toThrow(\App\Application\Exceptions\BusinessException::class, 'manager PIN approval');
});

test('ambiguous settlement succeeds with correct manager PIN', function () {
    $order = createServedOrder();
    $orderId = (int) $order['id'];

    $createIntent = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $createIntent->execute($orderId);

    DB::table('orders')->where('id', $orderId)->update(['total' => 999.99]);

    $managerPinHash = Hash::make('1234');

    $confirm = app(\App\Application\Payment\ConfirmPaymentUseCase::class);
    $result = $confirm->execute(
        reference: $intent['reference'],
        hmacSignature: $intent['hmac_signature'],
        nonce: $intent['nonce'],
        method: 'cash',
        confirmedBy: 4,
        actorRole: 'manager',
        expectedVersion: (int) $order['version'],
        managerPin: '1234',
        managerPinHash: $managerPinHash,
    );

    expect($result['order_status'])->toBe('settled');

    $log = DB::table('privilege_escalation_logs')
        ->where('action', 'settle_ambiguous')
        ->where('order_id', $orderId)
        ->first();
    expect($log)->not->toBeNull();
});

test('ambiguous settlement fails with wrong manager PIN', function () {
    $order = createServedOrder();
    $orderId = (int) $order['id'];

    $createIntent = app(\App\Application\Payment\CreatePaymentIntentUseCase::class);
    $intent = $createIntent->execute($orderId);

    DB::table('orders')->where('id', $orderId)->update(['total' => 999.99]);

    $confirm = app(\App\Application\Payment\ConfirmPaymentUseCase::class);

    expect(fn () => $confirm->execute(
        reference: $intent['reference'],
        hmacSignature: $intent['hmac_signature'],
        nonce: $intent['nonce'],
        method: 'cash',
        confirmedBy: 4,
        actorRole: 'manager',
        expectedVersion: (int) $order['version'],
        managerPin: '0000',
        managerPinHash: Hash::make('1234'),
    ))->toThrow(\App\Application\Exceptions\BusinessException::class, 'Incorrect manager PIN');
});

test('step-up verifier recognizes settle_ambiguous as requiring step-up', function () {
    $verifier = new \App\Domain\Auth\StepUpVerifier();
    expect($verifier->requiresStepUp('settle_ambiguous'))->toBeTrue();
});
