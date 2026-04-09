<?php

declare(strict_types=1);

namespace App\Livewire\Order;

use App\Application\Order\TransitionOrderUseCase;
use App\Domain\Order\Exceptions\StaleVersionException;
use App\Domain\Order\OrderStatus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class OrderList extends Component
{
    public string $statusFilter = '';
    public array $orders = [];
    public string $managerPin = '';
    public ?int $pendingActionOrderId = null;
    public string $pendingAction = '';
    public string $cancelReason = '';
    public bool $showPinModal = false;
    public ?string $errorMessage = null;
    public ?string $successMessage = null;
    public bool $showVersionConflict = false;
    public ?int $conflictOrderId = null;
    public ?string $conflictCurrentStatus = null;

    // Manual discount override
    public bool $showDiscountModal = false;
    public ?int $discountOrderId = null;
    public float $discountAmount = 0;
    public string $discountPin = '';
    public string $discountReason = '';

    public function mount(): void
    {
        $this->loadOrders();
    }

    public function updatedStatusFilter(): void
    {
        $this->loadOrders();
    }

    public function loadOrders(): void
    {
        $query = DB::table('orders')
            ->orderByDesc('created_at');

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        } else {
            $query->whereIn('status', [
                'pending_confirmation',
                'in_preparation',
                'served',
            ]);
        }

        $this->orders = $query->limit(50)->get()->map(fn ($o) => (array) $o)->toArray();
    }

    public function transitionOrder(int $orderId, string $targetStatus): void
    {
        $this->errorMessage = null;
        $this->successMessage = null;

        $order = DB::table('orders')->find($orderId);
        if (!$order) {
            $this->errorMessage = 'Order not found.';
            return;
        }

        $currentStatus = OrderStatus::from($order->status);
        $target = OrderStatus::from($targetStatus);

        // Check if step-up is needed
        if ($target === OrderStatus::Canceled && $currentStatus->requiresStepUpForCancel()) {
            $this->pendingActionOrderId = $orderId;
            $this->pendingAction = $targetStatus;
            $this->showPinModal = true;
            return;
        }

        $this->executeTransition($orderId, $targetStatus);
    }

    public function confirmWithPin(): void
    {
        if (!$this->pendingActionOrderId || !$this->pendingAction) {
            return;
        }

        $this->executeTransition(
            $this->pendingActionOrderId,
            $this->pendingAction,
            $this->managerPin,
            $this->cancelReason,
        );

        $this->showPinModal = false;
        $this->managerPin = '';
        $this->cancelReason = '';
        $this->pendingActionOrderId = null;
        $this->pendingAction = '';
    }

    public function cancelPinModal(): void
    {
        $this->showPinModal = false;
        $this->managerPin = '';
        $this->cancelReason = '';
        $this->pendingActionOrderId = null;
        $this->pendingAction = '';
    }

    public function openDiscountOverride(int $orderId): void
    {
        $this->showDiscountModal = true;
        $this->discountOrderId = $orderId;
        $this->discountAmount = 0;
        $this->discountPin = '';
        $this->discountReason = '';
        $this->errorMessage = null;
    }

    public function cancelDiscountModal(): void
    {
        $this->showDiscountModal = false;
        $this->discountOrderId = null;
    }

    public function applyDiscountOverride(): void
    {
        $this->errorMessage = null;

        if ($this->discountAmount <= 0) {
            $this->errorMessage = 'Discount amount must be positive.';
            return;
        }

        $order = DB::table('orders')->find($this->discountOrderId);
        if (!$order) {
            $this->errorMessage = 'Order not found.';
            return;
        }

        // Route through API for single authoritative path with version control
        $api = app(\App\Infrastructure\Api\InternalApiDispatcher::class);
        $result = $api->post("/orders/{$this->discountOrderId}/discount", [
            'amount' => round($this->discountAmount, 2),
            'expected_version' => (int) $order->version,
            'manager_pin' => $this->discountPin ?: null,
            'reason' => $this->discountReason ?: null,
        ]);

        if ($result['status'] === 409) {
            $this->errorMessage = 'Version conflict: order was modified. Please refresh and try again.';
            $this->loadOrders();
            return;
        }

        if ($result['status'] >= 400) {
            $this->errorMessage = $result['body']['message'] ?? 'Failed to apply discount.';
            return;
        }

        $this->successMessage = "Discount applied to order #{$order->order_number}.";
        $this->showDiscountModal = false;
        $this->discountOrderId = null;
        $this->loadOrders();
    }

    public function dismissConflict(): void
    {
        $this->showVersionConflict = false;
        $this->conflictOrderId = null;
        $this->conflictCurrentStatus = null;
        $this->loadOrders();
    }

    private function executeTransition(int $orderId, string $targetStatus, ?string $pin = null, ?string $cancelReason = null): void
    {
        $this->showVersionConflict = false;
        $this->conflictOrderId = null;
        $this->conflictCurrentStatus = null;

        try {
            $user = auth()->user();
            $order = DB::table('orders')->find($orderId);

            $managerPinHash = null;
            if ($pin && $user->manager_pin) {
                $managerPinHash = $user->manager_pin;
            }

            $useCase = app(TransitionOrderUseCase::class);
            $useCase->execute(
                orderId: $orderId,
                targetStatus: $targetStatus,
                expectedVersion: (int) $order->version,
                actorRole: $user->role,
                actorId: $user->id,
                managerPin: $pin,
                managerPinHash: $managerPinHash,
                cancelReason: $cancelReason,
            );

            $this->successMessage = "Order #{$order->order_number} updated to " . OrderStatus::from($targetStatus)->label();
            $this->loadOrders();
        } catch (StaleVersionException $e) {
            $this->showVersionConflict = true;
            $this->conflictOrderId = $orderId;
            $this->conflictCurrentStatus = $e->currentStatus;
            $this->errorMessage = null;
            $this->loadOrders();
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
            $this->loadOrders();
        }
    }

    public function render()
    {
        return view('livewire.order.order-list');
    }
}
