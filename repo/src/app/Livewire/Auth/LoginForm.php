<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Domain\Risk\CaptchaTriggerEvaluator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class LoginForm extends Component
{
    public string $username = '';
    public string $password = '';
    public bool $showCaptcha = false;
    public string $captchaQuestion = '';
    public string $captchaAnswer = '';
    private int $captchaExpected = 0;

    public function mount(): void
    {
        $this->checkCaptchaRequired();
    }

    public function login(): void
    {
        $this->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Check CAPTCHA if required
        if ($this->showCaptcha) {
            $expected = Cache::get('captcha_answer:' . session()->getId());
            if ((int) $this->captchaAnswer !== $expected) {
                $this->addError('captchaAnswer', 'Incorrect answer. Please try again.');
                $this->generateCaptcha();
                return;
            }
        }

        $ip = request()->ip() ?? 'unknown';
        $fingerprintHash = request()->attributes->get('device_fingerprint_hash', 'unknown');

        // Check username blacklist before attempting auth
        $isBlacklisted = DB::table('security_blacklists')
            ->where('type', 'username')
            ->where('value', $this->username)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($isBlacklisted) {
            $this->addError('username', 'Invalid username or password.');
            return;
        }

        if (Auth::attempt(['username' => $this->username, 'password' => $this->password, 'is_active' => true])) {
            session()->regenerate();

            // Clear failed login count
            Cache::forget("failed_logins:device:{$fingerprintHash}");
            Cache::forget("failed_logins:ip:{$ip}");

            // Log successful login
            DB::table('rule_hit_logs')->insert([
                'type' => 'login_success',
                'device_fingerprint_id' => request()->attributes->get('device_fingerprint_id'),
                'ip_address' => $ip,
                'details' => json_encode(['username' => $this->username]),
                'created_at' => now(),
            ]);

            // Redirect based on role
            $user = Auth::user();
            $redirect = match ($user->role) {
                'administrator' => '/admin/dashboard',
                'manager' => '/staff/orders',
                'cashier' => '/staff/orders',
                'kitchen' => '/staff/orders',
                default => '/staff/orders',
            };

            $this->redirect($redirect, navigate: false);
            return;
        }

        // Increment failed login count
        $deviceKey = "failed_logins:device:{$fingerprintHash}";
        $ipKey = "failed_logins:ip:{$ip}";
        Cache::put($deviceKey, (int) Cache::get($deviceKey, 0) + 1, now()->addHour());
        Cache::put($ipKey, (int) Cache::get($ipKey, 0) + 1, now()->addHour());

        // Log failed login
        try {
            DB::table('rule_hit_logs')->insert([
                'type' => 'login_failure',
                'device_fingerprint_id' => request()->attributes->get('device_fingerprint_id'),
                'ip_address' => $ip,
                'details' => json_encode(['username' => $this->username]),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // Don't let logging failures block login flow
        }

        $this->addError('username', 'Invalid username or password.');
        $this->checkCaptchaRequired();
    }

    private function checkCaptchaRequired(): void
    {
        $ip = request()->ip() ?? 'unknown';
        $fingerprintHash = request()->attributes->get('device_fingerprint_hash', 'unknown');

        $evaluator = new CaptchaTriggerEvaluator(
            failedLoginThreshold: config('harborbite.captcha.failed_login_threshold', 5),
        );

        $deviceFailures = (int) Cache::get("failed_logins:device:{$fingerprintHash}", 0);
        $ipFailures = (int) Cache::get("failed_logins:ip:{$ip}", 0);

        $this->showCaptcha = $evaluator->shouldTriggerForFailedLogins($deviceFailures)
            || $evaluator->shouldTriggerForFailedLogins($ipFailures);

        if ($this->showCaptcha) {
            // Log CAPTCHA trigger as immutable rule hit
            try {
                DB::table('rule_hit_logs')->insert([
                    'type' => 'captcha_triggered',
                    'device_fingerprint_id' => request()->attributes->get('device_fingerprint_id'),
                    'ip_address' => $ip,
                    'details' => json_encode([
                        'trigger' => 'failed_logins',
                        'device_failures' => $deviceFailures,
                        'ip_failures' => $ipFailures,
                    ]),
                    'created_at' => now(),
                ]);
            } catch (\Throwable) {
                // Don't block login flow for logging failures
            }

            $this->generateCaptcha();
        }
    }

    private function generateCaptcha(): void
    {
        $a = random_int(1, 20);
        $b = random_int(1, 20);
        $this->captchaQuestion = "{$a} + {$b} = ?";
        $this->captchaExpected = $a + $b;
        $this->captchaAnswer = '';

        Cache::put('captcha_answer:' . session()->getId(), $this->captchaExpected, now()->addMinutes(5));
    }

    public function render()
    {
        return view('livewire.auth.login-form')
            ->layout('components.layouts.kiosk', ['title' => 'Staff Login']);
    }
}
