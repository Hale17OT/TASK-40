<?php

declare(strict_types=1);

namespace App\Domain\Cart;

class CartValidator
{
    private const MAX_NOTE_LENGTH = 140;
    private const MIN_QUANTITY = 1;
    private const MAX_QUANTITY = 99;

    public function validateNote(?string $note): array
    {
        $errors = [];
        if ($note !== null && mb_strlen($note) > self::MAX_NOTE_LENGTH) {
            $errors[] = "Note must be " . self::MAX_NOTE_LENGTH . " characters or fewer.";
        }
        return $errors;
    }

    public function validateQuantity(int $quantity): array
    {
        $errors = [];
        if ($quantity < self::MIN_QUANTITY) {
            $errors[] = "Quantity must be at least " . self::MIN_QUANTITY . ".";
        }
        if ($quantity > self::MAX_QUANTITY) {
            $errors[] = "Quantity cannot exceed " . self::MAX_QUANTITY . ".";
        }
        return $errors;
    }

    public function getMaxNoteLength(): int
    {
        return self::MAX_NOTE_LENGTH;
    }
}
