<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

use RuntimeException;

class KitchenLockException extends RuntimeException
{
    public function __construct(string $itemName = '')
    {
        $msg = 'This item is being prepared and cannot be modified.';
        if ($itemName) {
            $msg = "Item '{$itemName}' is being prepared and cannot be modified.";
        }
        $msg .= ' A manager must authorize cancellation.';
        parent::__construct($msg);
    }
}
