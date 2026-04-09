<?php

use App\Domain\Risk\CaptchaTriggerEvaluator;

test('triggers after threshold failed logins', function () {
    $eval = new CaptchaTriggerEvaluator(failedLoginThreshold: 5);
    expect($eval->shouldTriggerForFailedLogins(5))->toBeTrue();
    expect($eval->shouldTriggerForFailedLogins(6))->toBeTrue();
});

test('does not trigger below threshold failed logins', function () {
    $eval = new CaptchaTriggerEvaluator(failedLoginThreshold: 5);
    expect($eval->shouldTriggerForFailedLogins(4))->toBeFalse();
    expect($eval->shouldTriggerForFailedLogins(0))->toBeFalse();
});

test('triggers for rapid repricing within window', function () {
    $eval = new CaptchaTriggerEvaluator(rapidRepricingThreshold: 3, rapidRepricingWindowSeconds: 60);
    $now = time();
    $timestamps = [$now, $now - 10, $now - 20];
    expect($eval->shouldTriggerForRapidRepricing($timestamps))->toBeTrue();
});

test('does not trigger for repricing outside window', function () {
    $eval = new CaptchaTriggerEvaluator(rapidRepricingThreshold: 3, rapidRepricingWindowSeconds: 60);
    $now = time();
    $timestamps = [$now, $now - 30, $now - 120]; // 3rd event outside 60s window from most recent
    expect($eval->shouldTriggerForRapidRepricing($timestamps))->toBeFalse();
});

test('does not trigger below repricing threshold', function () {
    $eval = new CaptchaTriggerEvaluator(rapidRepricingThreshold: 3, rapidRepricingWindowSeconds: 60);
    $now = time();
    $timestamps = [$now, $now - 10];
    expect($eval->shouldTriggerForRapidRepricing($timestamps))->toBeFalse();
});

test('getters return correct thresholds', function () {
    $eval = new CaptchaTriggerEvaluator(failedLoginThreshold: 7, rapidRepricingThreshold: 4);
    expect($eval->getFailedLoginThreshold())->toBe(7);
    expect($eval->getRapidRepricingThreshold())->toBe(4);
});
