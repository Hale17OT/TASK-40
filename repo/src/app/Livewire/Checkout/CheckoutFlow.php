<?php

declare(strict_types=1);

namespace App\Livewire\Checkout;

use App\Application\Cart\CartService;
use App\Domain\Auth\StepUpVerifier;
use App\Domain\Promotion\PromotionEvaluator;
use App\Infrastructure\Api\InternalApiDispatcher;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CheckoutFlow extends Component
{
    public ?array $cartSummary = null;
    public ?array $appliedPromotion = null;
    public bool $orderPlaced = false;
    public ?int $orderId = null;
    public ?string $orderNumber = null;
    public ?string $trackingToken = null;
    public ?string $errorMessage = null;

    // Manual discount override (staff-initiated, requires manager PIN if > $20)
    public bool $manualDiscountOverride = false;
    public float $manualDiscountAmount = 0;
    public bool $requiresDiscountOverridePin = false;
    public string $discountOverridePin = '';
    public bool $discountOverrideApproved = false;

    // Rapid re-pricing CAPTCHA
    public bool $requiresRepricingCaptcha = false;
    public bool $repricingCaptchaPassed = false;
    public string $captchaQuestion = '';
    public string $captchaAnswer = '';

    public function mount(): void
    {
        $this->loadCartSummary();
        $this->checkRepricingCaptcha();

        // Track checkout_started analytics event
        (new \App\Application\Analytics\TrackEventUseCase())->execute(
            eventType: 'checkout_started',
            sessionId: session()->getId(),
        );
    }

    public function approveDiscountOverride(): void
    {
        $this->errorMessage = null;
        $user = auth()->user();

        if (!$user || !$user->manager_pin) {
            $this->errorMessage = 'No manager PIN configured for your account.';
            return;
        }

        $verifier = app(StepUpVerifier::class);
        if (!$verifier->verify($this->discountOverridePin, $user->manager_pin)) {
            $this->errorMessage = 'Incorrect manager PIN.';
            return;
        }

        // Log the privilege escalation
        DB::table('privilege_escalation_logs')->insert([
            'action' => 'discount_override',
            'order_id' => null,
            'manager_id' => $user->id,
            'manager_pin_hash' => $user->manager_pin,
            'reason' => 'Discount over $20.00 approved',
            'metadata' => json_encode([
                'discount_amount' => $this->manualDiscountAmount,
            ]),
            'created_at' => now(),
        ]);

        $this->discountOverrideApproved = true;
        $this->requiresDiscountOverridePin = false;
        $this->discountOverridePin = '';
        $this->errorMessage = null;
    }

    /**
     * Staff-initiated manual discount override. Only this path requires manager PIN.
     * Automatic "best offer wins" promotions are applied without PIN.
     */
    public function applyManualDiscount(float $amount): void
    {
        $this->manualDiscountOverride = true;
        $this->manualDiscountAmount = $amount;
    }

    public function cancelDiscountOverride(): void
    {
        $this->requiresDiscountOverridePin = false;
        $this->discountOverridePin = '';
        $this->manualDiscountOverride = false;
        $this->manualDiscountAmount = 0;
    }

    public function verifyRepricingCaptcha(): void
    {
        $expected = \Illuminate\Support\Facades\Cache::get('repricing_captcha:' . session()->getId());
        if ((int) $this->captchaAnswer === $expected) {
            $this->repricingCaptchaPassed = true;
            $this->requiresRepricingCaptcha = false;
            $this->captchaAnswer = '';
            $this->errorMessage = null;

            // Log the CAPTCHA pass
            DB::table('rule_hit_logs')->insert([
                'type' => 'repricing_captcha_passed',
                'device_fingerprint_id' => request()->attributes->get('device_fingerprint_id'),
                'ip_address' => request()->ip(),
                'details' => json_encode(['session_id' => session()->getId()]),
                'created_at' => now(),
            ]);
        } else {
            $this->errorMessage = 'Incorrect answer. Please try again.';
            $this->generateRepricingCaptcha();
        }
    }

    private function checkRepricingCaptcha(): void
    {
        if ($this->repricingCaptchaPassed) {
            return;
        }

        $cartService = app(CartService::class);
        if ($cartService->requiresRepricingCaptcha(session()->getId())) {
            $this->requiresRepricingCaptcha = true;
            $this->generateRepricingCaptcha();

            // Log CAPTCHA trigger as immutable rule hit
            try {
                DB::table('rule_hit_logs')->insert([
                    'type' => 'captcha_triggered',
                    'device_fingerprint_id' => request()->attributes->get('device_fingerprint_id'),
                    'ip_address' => request()->ip(),
                    'details' => json_encode([
                        'trigger' => 'rapid_repricing',
                        'session_id' => session()->getId(),
                    ]),
                    'created_at' => now(),
                ]);
            } catch (\Throwable) {
                // Don't block checkout flow for logging failures
            }
        }
    }

    private function generateRepricingCaptcha(): void
    {
        $a = random_int(1, 20);
        $b = random_int(1, 20);
        $this->captchaQuestion = "{$a} + {$b} = ?";
        $this->captchaAnswer = '';
        \Illuminate\Support\Facades\Cache::put(
            'repricing_captcha:' . session()->getId(),
            $a + $b,
            now()->addMinutes(5),
        );
    }

    public function placeOrder(): void
    {
        $this->errorMessage = null;

        // Enforce rapid re-pricing CAPTCHA before proceeding
        if ($this->requiresRepricingCaptcha && !$this->repricingCaptchaPassed) {
            $this->errorMessage = 'Please complete the security check first.';
            return;
        }

        $cartService = app(CartService::class);
        $cart = $cartService->getCart(session()->getId());

        if (!$cart) {
            $this->errorMessage = 'Your cart is empty.';
            return;
        }

        // Re-check CAPTCHA requirement (may have changed since mount)
        if (!$this->repricingCaptchaPassed && $cartService->requiresRepricingCaptcha(session()->getId())) {
            $this->requiresRepricingCaptcha = true;
            $this->generateRepricingCaptcha();
            return;
        }

        // Manager PIN step-up is only required for explicit staff-initiated manual
        // discount overrides, NOT for automatic "best offer wins" promotions.
        // Automatic promotions are system-evaluated and always applied without PIN.
        if ($this->manualDiscountOverride && !$this->discountOverrideApproved) {
            $discountAmount = (float) $this->manualDiscountAmount;
            $verifier = app(StepUpVerifier::class);
            if ($verifier->requiresStepUp('discount_override', ['discount_amount' => $discountAmount])) {
                $this->requiresDiscountOverridePin = true;
                return;
            }
        }

        try {
            // Create order via REST API
            $api = app(InternalApiDispatcher::class);
            $result = $api->post('/orders', ['cart_id' => (int) $cart->id]);

            if ($result['status'] >= 400) {
                $this->errorMessage = $result['body']['message'] ?? 'Failed to create order.';
                return;
            }

            $order = $result['body']['data'];
            $orderId = (int) $order['id'];

            // Promotions are now applied atomically inside CreateOrderUseCase

            $this->orderPlaced = true;
            $this->orderId = $orderId;
            $this->orderNumber = $order['order_number'];
            $this->trackingToken = $order['tracking_token'];

            // Clear cart via REST API
            $api->delete('/cart');
            $this->dispatch('cart-updated', count: 0);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    private function loadCartSummary(): void
    {
        // Read cart via REST API
        $api = app(InternalApiDispatcher::class);
        $result = $api->get('/cart');
        $body = $result['body'];

        $items = $body['items'] ?? [];

        if (empty($items)) {
            $this->cartSummary = null;
            return;
        }

        $subtotal = 0;
        $itemList = [];
        $promoItems = [];

        foreach ($items as $item) {
            $lineTotal = (float) ($item['line_total'] ?? round(($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0), 2));
            $subtotal += $lineTotal;
            $itemList[] = [
                'name' => $item['name'] ?? '',
                'sku' => $item['sku'] ?? '',
                'quantity' => $item['quantity'] ?? 0,
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'line_total' => $lineTotal,
            ];
            $promoItems[] = [
                'sku' => $item['sku'] ?? '',
                'price' => (float) ($item['unit_price'] ?? 0),
                'quantity' => $item['quantity'] ?? 0,
                'name' => $item['name'] ?? '',
            ];
        }

        $subtotal = round($subtotal, 2);

        // Evaluate promotions (domain logic via service — not a mutation)
        $this->appliedPromotion = null;
        $promotions = DB::table('promotions')
            ->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->get()
            ->map(function ($p) {
                $arr = (array) $p;
                $arr['rules'] = json_decode($arr['rules'], true);
                return $arr;
            })
            ->toArray();

        if (!empty($promotions)) {
            $evaluator = app(PromotionEvaluator::class);
            $this->appliedPromotion = $evaluator->evaluate($promoItems, $promotions, $subtotal);

            if ($this->appliedPromotion) {
                $localTz = config('harborbite.timezone', 'America/Chicago');
                $matchedPromo = collect($promotions)->firstWhere('id', $this->appliedPromotion['promotion_id']);
                if ($matchedPromo) {
                    $startsAt = \Carbon\Carbon::parse($matchedPromo['starts_at'])->setTimezone($localTz);
                    $endsAt = \Carbon\Carbon::parse($matchedPromo['ends_at'])->setTimezone($localTz);
                    $this->appliedPromotion['time_window'] = $startsAt->format('m/d/Y g:i A') . ' – ' . $endsAt->format('m/d/Y g:i A');
                }
            }
        }

        $discount = $this->appliedPromotion ? (float) $this->appliedPromotion['discount_amount'] : 0;
        $estimatedTax = (float) ($body['totals']['tax'] ?? 0);

        $totalAfterDiscount = round($subtotal + $estimatedTax - $discount, 2);

        // Detect promotion-driven repricing: if the effective total (after promo
        // discount) changed from the last checkout load, record a repricing event.
        // This catches promo window open/close/change that the cart-level total
        // comparison cannot see (since CartService computes pre-discount totals).
        $sessionId = session()->getId();
        $promoCacheKey = "checkout_total_snapshot:{$sessionId}";
        $previousCheckoutTotal = \Illuminate\Support\Facades\Cache::get($promoCacheKey);
        if ($previousCheckoutTotal !== null && abs((float) $previousCheckoutTotal - $totalAfterDiscount) > 0.001) {
            app(CartService::class)->recordRepricingEvent($sessionId);
        }
        \Illuminate\Support\Facades\Cache::put($promoCacheKey, $totalAfterDiscount, now()->addMinutes(30));

        $this->cartSummary = [
            'items' => $itemList,
            'subtotal' => $subtotal,
            'estimated_tax' => $estimatedTax,
            'discount' => $discount,
            'total_after_discount' => $totalAfterDiscount,
            'item_count' => count($itemList),
        ];
    }

    public function render()
    {
        return view('livewire.checkout.checkout-flow');
    }
}
