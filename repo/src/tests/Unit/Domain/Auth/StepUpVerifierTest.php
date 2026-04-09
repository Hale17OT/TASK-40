<?php

use App\Domain\Auth\StepUpVerifier;

beforeEach(function () {
    $this->verifier = new StepUpVerifier();
});

test('correct PIN verifies successfully', function () {
    $hash = password_hash('1234', PASSWORD_BCRYPT);
    expect($this->verifier->verify('1234', $hash))->toBeTrue();
});

test('incorrect PIN fails verification', function () {
    $hash = password_hash('1234', PASSWORD_BCRYPT);
    expect($this->verifier->verify('0000', $hash))->toBeFalse();
});

test('empty PIN fails verification', function () {
    $hash = password_hash('1234', PASSWORD_BCRYPT);
    expect($this->verifier->verify('', $hash))->toBeFalse();
});

test('empty hash fails verification', function () {
    expect($this->verifier->verify('1234', ''))->toBeFalse();
});

test('cancel in preparation requires step-up', function () {
    expect($this->verifier->requiresStepUp('cancel_in_preparation'))->toBeTrue();
});

test('cancel served requires step-up', function () {
    expect($this->verifier->requiresStepUp('cancel_served'))->toBeTrue();
});

test('discount override over 20 requires step-up', function () {
    expect($this->verifier->requiresStepUp('discount_override', ['discount_amount' => 25.00]))->toBeTrue();
});

test('discount override under 20 does not require step-up', function () {
    expect($this->verifier->requiresStepUp('discount_override', ['discount_amount' => 15.00]))->toBeFalse();
});

test('settle ambiguous requires step-up', function () {
    expect($this->verifier->requiresStepUp('settle_ambiguous'))->toBeTrue();
});

test('unknown action does not require step-up', function () {
    expect($this->verifier->requiresStepUp('some_other_action'))->toBeFalse();
});
