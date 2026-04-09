<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Order\CreateOrderUseCase;
use App\Application\Order\TransitionOrderUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'cart_id' => 'required|integer',
        ]);

        // Verify the caller owns this cart (bound to their server session)
        $cart = \Illuminate\Support\Facades\DB::table('carts')
            ->where('id', (int) $request->input('cart_id'))
            ->first();

        if (!$cart || $cart->session_id !== session()->getId()) {
            return response()->json(['message' => 'Cart not found or access denied.'], 403);
        }

        $useCase = new CreateOrderUseCase();
        $order = $useCase->execute((int) $request->input('cart_id'));

        return response()->json([
            'data' => $order,
            'tracking_url' => url("/order/{$order['tracking_token']}"),
        ], 201);
    }

    public function show(string $trackingToken): JsonResponse
    {
        $order = DB::table('orders')
            ->where('tracking_token', $trackingToken)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        // Guest-safe DTO: only expose what a guest needs to track their order
        $items = DB::table('order_items')
            ->where('order_id', $order->id)
            ->get()
            ->map(fn ($item) => [
                'item_name' => $item->item_name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => round((float) $item->unit_price * $item->quantity, 2),
            ]);

        $statusLog = DB::table('order_status_logs')
            ->where('order_id', $order->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($log) => [
                'status' => $log->to_status,
                'timestamp' => $log->created_at,
            ]);

        return response()->json([
            'data' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'subtotal' => (float) $order->subtotal,
                'tax' => (float) $order->tax,
                'discount' => (float) $order->discount,
                'total' => (float) $order->total,
                'created_at' => $order->created_at,
            ],
            'items' => $items,
            'status_log' => $statusLog,
        ]);
    }

    public function showDetail(int $orderId): JsonResponse
    {
        $order = DB::table('orders')->where('id', $orderId)->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found.'], 404);
        }

        $items = DB::table('order_items')
            ->where('order_id', $order->id)
            ->get()
            ->map(fn ($item) => (array) $item);

        $statusLog = DB::table('order_status_logs')
            ->where('order_id', $order->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($log) => (array) $log);

        return response()->json([
            'data' => (array) $order,
            'items' => $items,
            'status_log' => $statusLog,
        ]);
    }

    public function transition(Request $request, int $orderId): JsonResponse
    {
        $request->validate([
            'target_status' => 'required|string|in:pending_confirmation,in_preparation,served,settled,canceled',
            'expected_version' => 'required|integer',
            'manager_pin' => 'nullable|string',
            'cancel_reason' => 'nullable|string',
        ]);

        $user = $request->user();
        $managerPinHash = null;
        if ($request->input('manager_pin') && $user->manager_pin) {
            $managerPinHash = $user->manager_pin;
        }

        $useCase = app(TransitionOrderUseCase::class);
        $order = $useCase->execute(
            orderId: $orderId,
            targetStatus: $request->input('target_status'),
            expectedVersion: (int) $request->input('expected_version'),
            actorRole: $user->role,
            actorId: $user->id,
            managerPin: $request->input('manager_pin'),
            managerPinHash: $managerPinHash,
            cancelReason: $request->input('cancel_reason'),
        );

        return response()->json(['data' => $order]);
    }

    public function applyDiscount(Request $request, int $orderId): JsonResponse
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'expected_version' => 'required|integer',
            'manager_pin' => 'nullable|string',
            'reason' => 'nullable|string',
        ]);

        $user = $request->user();
        $amount = round((float) $request->input('amount'), 2);

        $verifier = app(\App\Domain\Auth\StepUpVerifier::class);
        if ($verifier->requiresStepUp('discount_override', ['discount_amount' => $amount])) {
            if (!$request->input('manager_pin') || !$user->manager_pin) {
                return response()->json([
                    'message' => 'Manager PIN required for discounts over $20.00.',
                    'error_code' => 'STEP_UP_REQUIRED',
                ], 403);
            }
            if (!$verifier->verify($request->input('manager_pin'), $user->manager_pin)) {
                return response()->json([
                    'message' => 'Incorrect manager PIN.',
                    'error_code' => 'STEP_UP_FAILED',
                ], 403);
            }
        }

        $expectedVersion = (int) $request->input('expected_version');

        // Atomic compare-and-swap inside transaction to prevent lost updates
        return DB::transaction(function () use ($orderId, $amount, $expectedVersion, $user, $request) {
            $order = DB::table('orders')->where('id', $orderId)->lockForUpdate()->first();

            if (!$order) {
                return response()->json(['message' => 'Order not found.'], 404);
            }

            if ((int) $order->version !== $expectedVersion) {
                return response()->json([
                    'message' => 'Version conflict: order was modified by another user.',
                    'error_code' => 'STALE_VERSION',
                    'current_version' => (int) $order->version,
                ], 409);
            }

            $newTotal = round((float) $order->subtotal + (float) $order->tax - $amount, 2);
            $newVersion = (int) $order->version + 1;

            $affected = DB::table('orders')
                ->where('id', $orderId)
                ->where('version', $expectedVersion)
                ->update([
                    'discount' => $amount,
                    'total' => max(0, $newTotal),
                    'version' => $newVersion,
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                return response()->json([
                    'message' => 'Version conflict: order was modified by another user.',
                    'error_code' => 'STALE_VERSION',
                ], 409);
            }

            DB::table('privilege_escalation_logs')->insert([
                'action' => 'discount_override',
                'order_id' => $orderId,
                'manager_id' => $user->id,
                'manager_pin_hash' => $user->manager_pin,
                'reason' => $request->input('reason', "Manual discount of \${$amount}"),
                'metadata' => json_encode([
                    'discount_amount' => $amount,
                    'original_total' => (float) $order->total,
                    'new_total' => max(0, $newTotal),
                ]),
                'created_at' => now(),
            ]);

            return response()->json([
                'data' => (array) DB::table('orders')->find($orderId),
            ]);
        });
    }
}
