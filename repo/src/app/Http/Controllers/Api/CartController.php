<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Cart\CartService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $sessionId = session()->getId();
        $details = $this->cartService->loadCartDetails($sessionId);

        return response()->json([
            'data' => $this->cartService->getCart($sessionId),
            'items' => $details['items'],
            'totals' => [
                'subtotal' => $details['subtotal'],
                'tax' => $details['tax'],
                'total' => $details['total'],
            ],
            'tax_breakdown' => $details['taxBreakdown'],
            'price_changes' => $details['priceChanges'],
        ]);
    }

    public function addItem(Request $request): JsonResponse
    {
        $request->validate([
            'menu_item_id' => 'required|integer',
        ]);

        $sessionId = session()->getId();
        $fingerprintId = $request->attributes->get('device_fingerprint_id');

        $result = $this->cartService->addItem($sessionId, (int) $request->input('menu_item_id'), $fingerprintId);

        if (isset($result['error'])) {
            $code = str_contains($result['error'], 'no longer available') ? 404 : 422;
            return response()->json(['message' => $result['error']], $code);
        }

        return response()->json(['message' => 'Item added to cart.'], 201);
    }

    public function updateItem(Request $request, int $cartItemId): JsonResponse
    {
        $sessionId = session()->getId();
        $cart = $this->cartService->getCart($sessionId);

        if (!$cart) {
            return response()->json(['message' => 'Cart not found.'], 404);
        }

        if ($request->has('quantity')) {
            $result = $this->cartService->updateQuantity($cartItemId, (int) $cart->id, (int) $request->input('quantity'));
            if (isset($result['error'])) {
                return response()->json(['message' => $result['error']], 422);
            }
        }

        if ($request->has('note')) {
            $result = $this->cartService->updateNote($cartItemId, (int) $cart->id, $request->input('note'));
            if (isset($result['error'])) {
                return response()->json(['message' => $result['error']], 422);
            }
        }

        if ($request->has('flavor_preference')) {
            $this->cartService->updateFlavor($cartItemId, (int) $cart->id, $request->input('flavor_preference'));
        }

        return response()->json(['message' => 'Cart item updated.']);
    }

    public function removeItem(Request $request, int $cartItemId): JsonResponse
    {
        $sessionId = session()->getId();
        $cart = $this->cartService->getCart($sessionId);

        if (!$cart) {
            return response()->json(['message' => 'Cart not found.'], 404);
        }

        $this->cartService->removeItem($cartItemId, (int) $cart->id);

        return response()->json(['message' => 'Item removed.']);
    }

    public function clear(Request $request): JsonResponse
    {
        $sessionId = session()->getId();
        $cart = $this->cartService->getCart($sessionId);

        if ($cart) {
            $this->cartService->clearCart((int) $cart->id);
        }

        return response()->json(['message' => 'Cart cleared.']);
    }
}
