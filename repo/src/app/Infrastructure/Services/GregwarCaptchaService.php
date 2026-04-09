<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use Illuminate\Support\Facades\Cache;

class GregwarCaptchaService
{
    /**
     * Generate a math-based CAPTCHA challenge.
     * Falls back to math since Gregwar/Captcha requires GD which may not be configured.
     */
    public function generate(string $sessionKey): array
    {
        $a = random_int(1, 20);
        $b = random_int(1, 20);
        $operator = random_int(0, 1) ? '+' : '-';

        if ($operator === '-' && $a < $b) {
            [$a, $b] = [$b, $a]; // Ensure positive result
        }

        $answer = $operator === '+' ? $a + $b : $a - $b;
        $question = "{$a} {$operator} {$b} = ?";

        Cache::put("captcha:{$sessionKey}", $answer, now()->addMinutes(5));

        return [
            'challenge_id' => $sessionKey,
            'question' => $question,
            'type' => 'math',
        ];
    }

    /**
     * Verify a CAPTCHA answer.
     */
    public function verify(string $sessionKey, string $answer): bool
    {
        $expected = Cache::get("captcha:{$sessionKey}");

        if ($expected === null) {
            return false;
        }

        $isCorrect = (int) $answer === $expected;

        if ($isCorrect) {
            Cache::forget("captcha:{$sessionKey}");
        }

        return $isCorrect;
    }
}
