<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

class StructuredLogFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        $data = [
            'timestamp' => $record->datetime->format('Y-m-d\TH:i:s.uP'),
            'level' => $record->level->name,
            'channel' => $record->channel,
            'message' => $record->message,
            'context' => $this->scrubSensitiveData($record->context),
        ];

        if (isset($record->extra['trace_id'])) {
            $data['trace_id'] = $record->extra['trace_id'];
        }

        return json_encode($data, JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function scrubSensitiveData(array $context): array
    {
        $sensitiveKeys = [
            'password', 'pin', 'manager_pin', 'secret',
            'hmac_key', 'token', 'nonce', 'note', 'notes',
            'app_key', 'credit_card', 'cvv',
        ];

        foreach ($context as $key => &$value) {
            if (is_array($value)) {
                $value = $this->scrubSensitiveData($value);
            } elseif (is_string($key) && in_array(strtolower($key), $sensitiveKeys, true)) {
                $value = '[REDACTED]';
            }
        }

        return $context;
    }
}
