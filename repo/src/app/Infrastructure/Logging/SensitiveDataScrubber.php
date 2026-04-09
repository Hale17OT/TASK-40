<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class SensitiveDataScrubber implements ProcessorInterface
{
    private const SENSITIVE_KEYS = [
        'password', 'pin', 'manager_pin', 'secret',
        'hmac_key', 'token', 'nonce', 'note', 'notes',
        'app_key', 'credit_card', 'cvv',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->scrub($record->context),
            extra: $this->scrub($record->extra),
        );
    }

    private function scrub(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = $this->scrub($value);
            } elseif (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $value = '[REDACTED]';
            }
        }

        return $data;
    }
}
