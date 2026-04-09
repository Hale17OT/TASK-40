<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Payment\ConfirmPaymentUseCase;
use App\Application\Payment\CreatePaymentIntentUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function createIntent(Request $request, CreatePaymentIntentUseCase $useCase): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|integer',
        ]);

        $intent = $useCase->execute((int) $request->input('order_id'));

        return response()->json(['data' => $intent], 201);
    }

    public function confirm(Request $request, ConfirmPaymentUseCase $useCase): JsonResponse
    {
        $request->validate([
            'reference' => 'required|string',
            'hmac_signature' => 'required|string',
            'nonce' => 'required|string',
            'method' => 'required|string|in:cash,card_manual',
            'expected_version' => 'required|integer',
            'notes' => 'nullable|string',
            'manager_pin' => 'nullable|string',
        ]);

        $user = $request->user();
        $managerPinHash = null;
        if ($request->input('manager_pin') && $user->manager_pin) {
            $managerPinHash = $user->manager_pin;
        }

        $result = $useCase->execute(
            reference: $request->input('reference'),
            hmacSignature: $request->input('hmac_signature'),
            nonce: $request->input('nonce'),
            method: $request->input('method'),
            confirmedBy: $user->id,
            actorRole: $user->role,
            expectedVersion: (int) $request->input('expected_version'),
            notes: $request->input('notes'),
            managerPin: $request->input('manager_pin'),
            managerPinHash: $managerPinHash,
        );

        return response()->json([
            'data' => $result,
        ], $result['idempotent'] ? 200 : 201);
    }
}
