<?php

use App\Domain\Order\OrderStatus;

test('pending confirmation can transition to in_preparation and canceled', function () {
    $transitions = OrderStatus::PendingConfirmation->transitions();
    expect($transitions)->toContain(OrderStatus::InPreparation);
    expect($transitions)->toContain(OrderStatus::Canceled);
    expect($transitions)->toHaveCount(2);
});

test('in preparation can transition to served and canceled', function () {
    $transitions = OrderStatus::InPreparation->transitions();
    expect($transitions)->toContain(OrderStatus::Served);
    expect($transitions)->toContain(OrderStatus::Canceled);
    expect($transitions)->toHaveCount(2);
});

test('served can transition to settled and canceled', function () {
    $transitions = OrderStatus::Served->transitions();
    expect($transitions)->toContain(OrderStatus::Settled);
    expect($transitions)->toContain(OrderStatus::Canceled);
    expect($transitions)->toHaveCount(2);
});

test('settled is terminal', function () {
    expect(OrderStatus::Settled->isTerminal())->toBeTrue();
    expect(OrderStatus::Settled->transitions())->toBeEmpty();
});

test('canceled is terminal', function () {
    expect(OrderStatus::Canceled->isTerminal())->toBeTrue();
    expect(OrderStatus::Canceled->transitions())->toBeEmpty();
});

test('canTransitionTo works correctly', function () {
    expect(OrderStatus::PendingConfirmation->canTransitionTo(OrderStatus::InPreparation))->toBeTrue();
    expect(OrderStatus::PendingConfirmation->canTransitionTo(OrderStatus::Served))->toBeFalse();
    expect(OrderStatus::PendingConfirmation->canTransitionTo(OrderStatus::Settled))->toBeFalse();
});

test('labels return human-readable names', function () {
    expect(OrderStatus::PendingConfirmation->label())->toBe('Pending Confirmation');
    expect(OrderStatus::InPreparation->label())->toBe('In Preparation');
    expect(OrderStatus::Served->label())->toBe('Served');
    expect(OrderStatus::Settled->label())->toBe('Settled');
    expect(OrderStatus::Canceled->label())->toBe('Canceled');
});

test('cancel from in_preparation requires step-up', function () {
    expect(OrderStatus::InPreparation->requiresStepUpForCancel())->toBeTrue();
});

test('cancel from served requires step-up', function () {
    expect(OrderStatus::Served->requiresStepUpForCancel())->toBeTrue();
});

test('cancel from pending does not require step-up', function () {
    expect(OrderStatus::PendingConfirmation->requiresStepUpForCancel())->toBeFalse();
});
