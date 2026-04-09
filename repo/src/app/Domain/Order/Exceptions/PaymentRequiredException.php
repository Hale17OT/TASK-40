<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

class PaymentRequiredException extends \RuntimeException
{
    public function __construct(string $message = 'Order cannot be settled without a confirmed payment.')
    {
        parent::__construct($message);
    }
}
