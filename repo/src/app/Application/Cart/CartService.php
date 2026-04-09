<?php

declare(strict_types=1);

namespace App\Application\Cart;

use App\Application\Analytics\TrackEventUseCase;
use App\Domain\Cart\CartValidator;
use App\Domain\Cart\TaxCalculator;
use App\Domain\Risk\CaptchaTriggerEvaluator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function getOrCreateCart(string $sessionId, ?int $fingerprintId = null): object
    {
        $cart = DB::table('carts')->where('session_id', $sessionId)->first();

        if (!$cart) {
            $cartId = DB::table('carts')->insertGetId([
                'session_id' => $sessionId,
                'device_fingerprint_id' => $fingerprintId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $cart = DB::table('carts')->find($cartId);
        }

        return $cart;
    }

    public function getCart(string $sessionId): ?object
    {
        return DB::table('carts')->where('session_id', $sessionId)->first();
    }

    public function addItem(string $sessionId, int $menuItemId, ?int $fingerprintId = null): array
    {
        $menuItem = DB::table('menu_items')
            ->where('id', $menuItemId)
            ->where('is_active', true)
            ->first();

        if (!$menuItem) {
            return ['error' => 'This item is no longer available.'];
        }

        $cart = $this->getOrCreateCart($sessionId, $fingerprintId);

        $existing = DB::table('cart_items')
            ->where('cart_id', $cart->id)
            ->where('menu_item_id', $menuItemId)
            ->first();

        if ($existing) {
            $validator = new CartValidator();
            $newQuantity = $existing->quantity + 1;
            $errors = $validator->validateQuantity($newQuantity);
            if (!empty($errors)) {
                return ['error' => $errors[0]];
            }
            DB::table('cart_items')
                ->where('id', $existing->id)
                ->update(['quantity' => $newQuantity, 'updated_at' => now()]);
        } else {
            DB::table('cart_items')->insert([
                'cart_id' => $cart->id,
                'menu_item_id' => $menuItemId,
                'quantity' => 1,
                'flavor_preference' => null,
                'note' => null,
                'unit_price_snapshot' => $menuItem->price,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Track analytics event
        (new TrackEventUseCase())->execute(
            eventType: 'add_to_cart',
            sessionId: $sessionId,
            payload: ['menu_item_id' => $menuItemId, 'price' => (float) $menuItem->price],
        );

        return ['success' => true];
    }

    public function updateQuantity(int $cartItemId, int $cartId, int $quantity): array
    {
        $validator = new CartValidator();
        $errors = $validator->validateQuantity($quantity);
        if (!empty($errors)) {
            return ['error' => $errors[0]];
        }

        DB::table('cart_items')
            ->where('id', $cartItemId)
            ->where('cart_id', $cartId)
            ->update(['quantity' => $quantity, 'updated_at' => now()]);

        return ['success' => true];
    }

    public function updateNote(int $cartItemId, int $cartId, string $note): array
    {
        $validator = new CartValidator();
        $errors = $validator->validateNote($note);
        if (!empty($errors)) {
            return ['error' => $errors[0]];
        }

        $encryptedNote = $note !== '' ? Crypt::encryptString($note) : null;

        DB::table('cart_items')
            ->where('id', $cartItemId)
            ->where('cart_id', $cartId)
            ->update(['note' => $encryptedNote, 'updated_at' => now()]);

        return ['success' => true];
    }

    public function updateFlavor(int $cartItemId, int $cartId, string $flavor): void
    {
        DB::table('cart_items')
            ->where('id', $cartItemId)
            ->where('cart_id', $cartId)
            ->update(['flavor_preference' => $flavor, 'updated_at' => now()]);
    }

    public function removeItem(int $cartItemId, int $cartId): void
    {
        DB::table('cart_items')
            ->where('id', $cartItemId)
            ->where('cart_id', $cartId)
            ->delete();
    }

    public function clearCart(int $cartId): void
    {
        DB::table('cart_items')->where('cart_id', $cartId)->delete();
    }

    /**
     * Load cart items with enriched data (decrypted notes, prices, tax).
     *
     * @return array{items: array, subtotal: float, tax: float, total: float, taxBreakdown: array, priceChanges: array}
     */
    public function loadCartDetails(string $sessionId): array
    {
        $cart = $this->getCart($sessionId);
        $empty = ['items' => [], 'subtotal' => 0, 'tax' => 0, 'total' => 0, 'taxBreakdown' => [], 'priceChanges' => []];

        if (!$cart) {
            return $empty;
        }

        $items = DB::table('cart_items')
            ->join('menu_items', 'cart_items.menu_item_id', '=', 'menu_items.id')
            ->where('cart_items.cart_id', $cart->id)
            ->select([
                'cart_items.id',
                'cart_items.menu_item_id',
                'cart_items.quantity',
                'cart_items.flavor_preference',
                'cart_items.note',
                'cart_items.unit_price_snapshot',
                'menu_items.name',
                'menu_items.price as current_price',
                'menu_items.tax_category',
                'menu_items.sku',
                'menu_items.is_active',
            ])
            ->get();

        $cartItems = [];
        $taxItems = [];
        $priceChanges = [];

        foreach ($items as $item) {
            $priceChanged = abs((float) $item->unit_price_snapshot - (float) $item->current_price) > 0.001;
            if ($priceChanged) {
                $priceChanges[$item->id] = [
                    'old' => (float) $item->unit_price_snapshot,
                    'new' => (float) $item->current_price,
                ];
            }

            $decryptedNote = null;
            if ($item->note) {
                try {
                    $decryptedNote = Crypt::decryptString($item->note);
                } catch (\Throwable) {
                    $decryptedNote = $item->note;
                }
            }

            $cartItems[] = [
                'id' => $item->id,
                'menu_item_id' => $item->menu_item_id,
                'name' => $item->name,
                'sku' => $item->sku,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price_snapshot,
                'current_price' => (float) $item->current_price,
                'line_total' => round((float) $item->unit_price_snapshot * $item->quantity, 2),
                'flavor_preference' => $item->flavor_preference,
                'note' => $decryptedNote,
                'tax_category' => $item->tax_category,
                'is_active' => (bool) $item->is_active,
                'price_changed' => $priceChanged,
            ];

            $taxItems[] = [
                'name' => $item->name,
                'price' => (float) $item->unit_price_snapshot,
                'quantity' => $item->quantity,
                'tax_category' => $item->tax_category,
            ];
        }

        $subtotal = round(array_sum(array_column($cartItems, 'line_total')), 2);

        $taxRules = DB::table('tax_rules')
            ->whereDate('effective_from', '<=', now())
            ->where(function ($q) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', now());
            })
            ->get(['category', 'rate'])
            ->map(fn ($r) => (array) $r)
            ->toArray();

        $calculator = new TaxCalculator($taxRules);
        $tax = $calculator->calculateCartTax($taxItems);
        $taxBreakdown = $calculator->getBreakdown($taxItems);
        $total = round($subtotal + $tax, 2);

        // Record a repricing event when pricing inputs actually changed, not on
        // every cart read. Two independent signals:
        //
        // 1. Item price drift: menu prices diverged from cart snapshots, meaning
        //    the guest's displayed price no longer matches the menu (priceChanges).
        // 2. Tax-rule shift: same snapshots but the computed total changed because
        //    a tax rate was added, removed, or modified between reads.
        //
        // Promotion-driven repricing is detected separately in CheckoutFlow where
        // promo evaluation happens.
        $totalCacheKey = "cart_total_snapshot:{$sessionId}";
        $previousTotal = Cache::get($totalCacheKey);
        $taxChanged = $previousTotal !== null && abs((float) $previousTotal - $total) > 0.001;

        if (!empty($priceChanges) || $taxChanged) {
            $this->recordRepricingEvent($sessionId);
        }
        Cache::put($totalCacheKey, $total, now()->addMinutes(30));

        return [
            'items' => $cartItems,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'taxBreakdown' => $taxBreakdown,
            'priceChanges' => $priceChanges,
        ];
    }

    /**
     * Record a repricing event timestamp for this session (for CAPTCHA evaluation).
     */
    public function recordRepricingEvent(string $sessionId): void
    {
        $cacheKey = "repricing_events:{$sessionId}";
        $timestamps = Cache::get($cacheKey, []);
        $timestamps[] = time();

        // Keep only events within the last 5 minutes to prevent unbounded growth
        $cutoff = time() - 300;
        $timestamps = array_values(array_filter($timestamps, fn (int $ts) => $ts >= $cutoff));

        Cache::put($cacheKey, $timestamps, now()->addMinutes(5));
    }

    /**
     * Check whether rapid repricing CAPTCHA should be triggered for this session.
     */
    public function requiresRepricingCaptcha(string $sessionId): bool
    {
        $cacheKey = "repricing_events:{$sessionId}";
        $timestamps = Cache::get($cacheKey, []);

        if (empty($timestamps)) {
            return false;
        }

        $evaluator = app(CaptchaTriggerEvaluator::class);
        return $evaluator->shouldTriggerForRapidRepricing($timestamps);
    }
}
