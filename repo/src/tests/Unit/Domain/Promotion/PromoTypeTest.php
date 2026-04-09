<?php

use App\Domain\Promotion\PromoType;

test('all four types defined', function () {
    expect(PromoType::cases())->toHaveCount(4);
});

test('bogo and percentage_off_second are item level', function () {
    expect(PromoType::Bogo->isItemLevel())->toBeTrue();
    expect(PromoType::PercentageOffSecond->isItemLevel())->toBeTrue();
    expect(PromoType::PercentageOff->isItemLevel())->toBeFalse();
    expect(PromoType::FlatDiscount->isItemLevel())->toBeFalse();
});

test('percentage_off and flat_discount are cart level', function () {
    expect(PromoType::PercentageOff->isCartLevel())->toBeTrue();
    expect(PromoType::FlatDiscount->isCartLevel())->toBeTrue();
    expect(PromoType::Bogo->isCartLevel())->toBeFalse();
});

test('labels are human readable', function () {
    expect(PromoType::Bogo->label())->toBe('Buy One Get One');
    expect(PromoType::PercentageOff->label())->toBe('Percentage Off');
});
