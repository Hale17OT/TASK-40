<?php

declare(strict_types=1);

namespace App\Livewire\Order;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class OrderTracker extends Component
{
    #[Locked]
    public ?string $trackingToken = null;
    public ?array $order = null;
    public array $items = [];
    public array $statusLog = [];
    public bool $showConflict = false;
    public string $conflictMessage = '';

    public function mount(string $trackingToken): void
    {
        $this->trackingToken = $trackingToken;
        $this->loadOrder();
    }

    public function loadOrder(): void
    {
        $order = DB::table('orders')
            ->where('tracking_token', $this->trackingToken)
            ->first();

        if (!$order) {
            $this->order = null;
            return;
        }

        $orderId = (int) $order->id;

        // Guest-safe: only expose fields needed by the view
        $this->order = [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'subtotal' => (float) $order->subtotal,
            'tax' => (float) $order->tax,
            'discount' => (float) $order->discount,
            'total' => (float) $order->total,
            'created_at' => $order->created_at,
        ];

        $this->items = DB::table('order_items')
            ->where('order_id', $orderId)
            ->get()
            ->map(fn ($item) => [
                'item_name' => $item->item_name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => round((float) $item->unit_price * $item->quantity, 2),
                'locked_at' => $item->locked_at,
            ])
            ->toArray();

        $this->statusLog = DB::table('order_status_logs')
            ->where('order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($log) => [
                'status' => $log->to_status,
                'timestamp' => $log->created_at,
            ])
            ->toArray();
    }

    public function render()
    {
        return view('livewire.order.order-tracker');
    }
}
