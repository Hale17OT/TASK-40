<?php

use App\Domain\Order\OrderStateMachine;
use App\Domain\Order\OrderStatus;
use App\Domain\Order\Exceptions\StaleVersionException;
use App\Domain\Order\Exceptions\InvalidTransitionException;
use App\Domain\Order\Exceptions\InsufficientRoleException;

beforeEach(function () {
    $this->machine = new OrderStateMachine();
});

test('valid transition increments version', function () {
    $newVersion = $this->machine->transition(
        OrderStatus::PendingConfirmation,
        OrderStatus::InPreparation,
        currentVersion: 1,
        expectedVersion: 1,
        actorRole: 'cashier',
    );
    expect($newVersion)->toBe(2);
});

test('stale version throws exception', function () {
    $this->machine->transition(
        OrderStatus::PendingConfirmation,
        OrderStatus::InPreparation,
        currentVersion: 3,
        expectedVersion: 1,
        actorRole: 'cashier',
    );
})->throws(StaleVersionException::class);

test('stale version exception contains correct data', function () {
    try {
        $this->machine->transition(
            OrderStatus::PendingConfirmation,
            OrderStatus::InPreparation,
            currentVersion: 5,
            expectedVersion: 3,
            actorRole: 'cashier',
        );
    } catch (StaleVersionException $e) {
        expect($e->currentVersion)->toBe(5);
        expect($e->expectedVersion)->toBe(3);
        expect($e->currentStatus)->toBe('pending_confirmation');
    }
});

test('invalid transition throws exception', function () {
    $this->machine->transition(
        OrderStatus::PendingConfirmation,
        OrderStatus::Settled,
        currentVersion: 1,
        expectedVersion: 1,
        actorRole: 'cashier',
    );
})->throws(InvalidTransitionException::class);

test('kitchen cannot confirm order', function () {
    $this->machine->transition(
        OrderStatus::PendingConfirmation,
        OrderStatus::InPreparation,
        currentVersion: 1,
        expectedVersion: 1,
        actorRole: 'kitchen',
    );
})->throws(InsufficientRoleException::class);

test('kitchen can mark as served', function () {
    $newVersion = $this->machine->transition(
        OrderStatus::InPreparation,
        OrderStatus::Served,
        currentVersion: 2,
        expectedVersion: 2,
        actorRole: 'kitchen',
    );
    expect($newVersion)->toBe(3);
});

test('cancel from in_preparation without step-up throws exception', function () {
    $this->machine->transition(
        OrderStatus::InPreparation,
        OrderStatus::Canceled,
        currentVersion: 2,
        expectedVersion: 2,
        actorRole: 'manager',
        stepUpVerified: false,
    );
})->throws(InsufficientRoleException::class);

test('cancel from in_preparation with step-up succeeds', function () {
    $newVersion = $this->machine->transition(
        OrderStatus::InPreparation,
        OrderStatus::Canceled,
        currentVersion: 2,
        expectedVersion: 2,
        actorRole: 'manager',
        stepUpVerified: true,
    );
    expect($newVersion)->toBe(3);
});

test('cancel from pending does not require step-up', function () {
    $newVersion = $this->machine->transition(
        OrderStatus::PendingConfirmation,
        OrderStatus::Canceled,
        currentVersion: 1,
        expectedVersion: 1,
        actorRole: 'cashier',
    );
    expect($newVersion)->toBe(2);
});

test('full happy path lifecycle', function () {
    $v = $this->machine->transition(OrderStatus::PendingConfirmation, OrderStatus::InPreparation, 1, 1, 'cashier');
    expect($v)->toBe(2);

    $v = $this->machine->transition(OrderStatus::InPreparation, OrderStatus::Served, 2, 2, 'kitchen');
    expect($v)->toBe(3);

    $v = $this->machine->transition(OrderStatus::Served, OrderStatus::Settled, 3, 3, 'cashier');
    expect($v)->toBe(4);
});

test('cannot transition from terminal state', function () {
    $this->machine->transition(
        OrderStatus::Settled,
        OrderStatus::Canceled,
        currentVersion: 4,
        expectedVersion: 4,
        actorRole: 'administrator',
    );
})->throws(InvalidTransitionException::class);

test('manager can perform all non-kitchen transitions', function () {
    $v = $this->machine->transition(OrderStatus::PendingConfirmation, OrderStatus::InPreparation, 1, 1, 'manager');
    expect($v)->toBe(2);
});
