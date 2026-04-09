<?php

use App\Infrastructure\Logging\SensitiveDataScrubber;
use App\Infrastructure\Logging\StructuredLogFormatter;
use Monolog\Level;
use Monolog\LogRecord;

test('sensitive data scrubber redacts password fields', function () {
    $scrubber = new SensitiveDataScrubber();

    $record = new LogRecord(
        datetime: new \DateTimeImmutable(),
        channel: 'test',
        level: Level::Info,
        message: 'test message',
        context: [
            'username' => 'admin',
            'password' => 'secret123',
            'manager_pin' => '9999',
            'note' => 'customer note',
            'credit_card' => '4111111111111111',
            'cvv' => '123',
            'token' => 'abc123',
            'hmac_key' => 'key123',
            'safe_field' => 'visible',
        ],
    );

    $result = $scrubber($record);

    expect($result->context['password'])->toBe('[REDACTED]');
    expect($result->context['manager_pin'])->toBe('[REDACTED]');
    expect($result->context['note'])->toBe('[REDACTED]');
    expect($result->context['credit_card'])->toBe('[REDACTED]');
    expect($result->context['cvv'])->toBe('[REDACTED]');
    expect($result->context['token'])->toBe('[REDACTED]');
    expect($result->context['hmac_key'])->toBe('[REDACTED]');
    expect($result->context['safe_field'])->toBe('visible');
    expect($result->context['username'])->toBe('admin');
});

test('sensitive data scrubber handles nested arrays', function () {
    $scrubber = new SensitiveDataScrubber();

    $record = new LogRecord(
        datetime: new \DateTimeImmutable(),
        channel: 'test',
        level: Level::Info,
        message: 'test',
        context: [
            'user' => [
                'name' => 'John',
                'password' => 'secret',
                'nested' => [
                    'pin' => '1234',
                    'safe' => 'visible',
                ],
            ],
        ],
    );

    $result = $scrubber($record);

    expect($result->context['user']['name'])->toBe('John');
    expect($result->context['user']['password'])->toBe('[REDACTED]');
    expect($result->context['user']['nested']['pin'])->toBe('[REDACTED]');
    expect($result->context['user']['nested']['safe'])->toBe('visible');
});

test('structured log formatter produces valid JSON with redaction', function () {
    $formatter = new StructuredLogFormatter();

    $record = new LogRecord(
        datetime: new \DateTimeImmutable(),
        channel: 'test',
        level: Level::Info,
        message: 'login attempt',
        context: [
            'username' => 'admin',
            'password' => 'secret123',
        ],
    );

    $output = $formatter->format($record);
    $decoded = json_decode($output, true);

    expect($decoded)->toBeArray();
    expect($decoded['message'])->toBe('login attempt');
    expect($decoded['context']['username'])->toBe('admin');
    expect($decoded['context']['password'])->toBe('[REDACTED]');
    expect($decoded['level'])->toBe('Info');
});

test('scrubber redacts extra fields too', function () {
    $scrubber = new SensitiveDataScrubber();

    $record = new LogRecord(
        datetime: new \DateTimeImmutable(),
        channel: 'test',
        level: Level::Info,
        message: 'test',
        context: [],
        extra: [
            'nonce' => 'should-be-redacted',
            'trace_id' => 'should-be-visible',
        ],
    );

    $result = $scrubber($record);

    expect($result->extra['nonce'])->toBe('[REDACTED]');
    expect($result->extra['trace_id'])->toBe('should-be-visible');
});
