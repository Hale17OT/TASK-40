<?php

declare(strict_types=1);

namespace App\Livewire\Manager;

use App\Application\Order\TransitionOrderUseCase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ReconciliationQueue extends Component
{
    public array $tickets = [];
    public string $filterStatus = 'open';

    // Resolve form
    public ?int $resolvingTicketId = null;
    public string $reasonCode = 'cash_verified';
    public string $receiptReference = '';

    public ?string $message = null;
    public ?string $error = null;

    public array $reasonCodes = [
        'cash_verified' => 'Cash Verified',
        'card_receipt_matched' => 'Card Receipt Matched',
        'pos_system_confirmed' => 'POS System Confirmed',
        'other' => 'Other',
    ];

    public function mount(): void
    {
        $this->loadTickets();
    }

    public function updatedFilterStatus(): void
    {
        $this->loadTickets();
    }

    public function openResolve(int $ticketId): void
    {
        $this->resolvingTicketId = $ticketId;
        $this->reasonCode = 'cash_verified';
        $this->receiptReference = '';
    }

    public function cancelResolve(): void
    {
        $this->resolvingTicketId = null;
    }

    public function resolveTicket(): void
    {
        $this->error = null;
        $this->message = null;

        if (!$this->resolvingTicketId) {
            $this->error = 'No ticket selected.';
            return;
        }

        if (!array_key_exists($this->reasonCode, $this->reasonCodes)) {
            $this->error = 'Invalid reason code.';
            return;
        }

        $ticket = DB::table('incident_tickets')->find($this->resolvingTicketId);
        if (!$ticket || $ticket->status !== 'open') {
            $this->error = 'Ticket not found or already resolved.';
            return;
        }

        // If there's an associated payment_intent in reconciling status, mark it confirmed
        if ($ticket->payment_intent_id) {
            DB::table('payment_intents')
                ->where('id', $ticket->payment_intent_id)
                ->where('status', 'reconciling')
                ->update([
                    'status' => 'confirmed',
                    'updated_at' => now(),
                ]);
        }

        // Transition the order to settled if it is still in served state
        $order = DB::table('orders')->find($ticket->order_id);
        if ($order && $order->status === 'served') {
            $hasConfirmedPayment = DB::table('payment_intents')
                ->where('order_id', $order->id)
                ->where('status', 'confirmed')
                ->exists();

            if (!$hasConfirmedPayment) {
                $this->error = 'Cannot resolve: no confirmed payment intent exists for this order.';
                return;
            }

            try {
                $transitionUseCase = app(TransitionOrderUseCase::class);
                $transitionUseCase->execute(
                    orderId: (int) $order->id,
                    targetStatus: 'settled',
                    expectedVersion: (int) $order->version,
                    actorRole: 'manager',
                    actorId: (int) auth()->id(),
                );
            } catch (\Throwable $e) {
                $this->error = 'Failed to settle order: ' . $e->getMessage();
                return;
            }
        }

        DB::table('incident_tickets')->where('id', $this->resolvingTicketId)->update([
            'status' => 'resolved',
            'resolution_reason_code' => $this->reasonCode,
            'receipt_reference' => trim($this->receiptReference) ? Crypt::encryptString(trim($this->receiptReference)) : null,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
            'updated_at' => now(),
        ]);

        $this->message = "Ticket #{$this->resolvingTicketId} resolved.";
        $this->resolvingTicketId = null;
        $this->loadTickets();
    }

    public function dismissTicket(int $id): void
    {
        $this->error = null;
        DB::table('incident_tickets')->where('id', $id)->where('status', 'open')->update([
            'status' => 'dismissed',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
            'updated_at' => now(),
        ]);

        $this->message = "Ticket #{$id} dismissed.";
        $this->loadTickets();
    }

    private function loadTickets(): void
    {
        $query = DB::table('incident_tickets')
            ->leftJoin('orders', 'incident_tickets.order_id', '=', 'orders.id')
            ->leftJoin('payment_intents', 'incident_tickets.payment_intent_id', '=', 'payment_intents.id')
            ->leftJoin('users as assignee', 'incident_tickets.assigned_to', '=', 'assignee.id')
            ->leftJoin('users as resolver', 'incident_tickets.resolved_by', '=', 'resolver.id')
            ->select([
                'incident_tickets.*',
                'orders.status as order_status',
                'payment_intents.amount as payment_amount',
                'payment_intents.reference as payment_reference',
                'payment_intents.status as payment_status',
                'assignee.name as assignee_name',
                'resolver.name as resolver_name',
            ])
            ->orderByDesc('incident_tickets.created_at');

        if ($this->filterStatus !== 'all') {
            $query->where('incident_tickets.status', $this->filterStatus);
        }

        $this->tickets = $query->limit(100)->get()->map(fn ($t) => (array) $t)->toArray();
    }

    public function render()
    {
        return view('livewire.manager.reconciliation-queue');
    }
}
