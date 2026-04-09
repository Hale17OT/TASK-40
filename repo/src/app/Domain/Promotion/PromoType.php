<?php

declare(strict_types=1);

namespace App\Domain\Promotion;

enum PromoType: string
{
    case PercentageOff = 'percentage_off';
    case FlatDiscount = 'flat_discount';
    case Bogo = 'bogo';
    case PercentageOffSecond = 'percentage_off_second';

    public function label(): string
    {
        return match ($this) {
            self::PercentageOff => 'Percentage Off',
            self::FlatDiscount => 'Flat Discount',
            self::Bogo => 'Buy One Get One',
            self::PercentageOffSecond => '% Off Second Item',
        };
    }

    public function isItemLevel(): bool
    {
        return in_array($this, [self::Bogo, self::PercentageOffSecond]);
    }

    public function isCartLevel(): bool
    {
        return in_array($this, [self::PercentageOff, self::FlatDiscount]);
    }
}
