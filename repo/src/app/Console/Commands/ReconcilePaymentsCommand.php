<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcilePaymentsCommand extends Command
{
    protected $signature = 'harborbite:reconcile-payments';
    protected $description = 'Detect stuck payment states and create incident tickets';

    public function handle(): int
    {
        $this->info('Reconciling payments...');

        // Find orders that are "served" with confirmed payment but not settled
        $stuckOrders = DB::table('orders')
            ->join('payment_intents', 'orders.id', '=', 'payment_intents.order_id')
            ->where('orders.status', 'served')
            ->where('payment_intents.status', 'confirmed')
            ->select('orders.id as order_id', 'payment_intents.id as payment_intent_id')
            ->get();

        foreach ($stuckOrders as $stuck) {
            $exists = DB::table('incident_tickets')
                ->where('order_id', $stuck->order_id)
                ->where('type', 'paid_not_settled')
                ->where('status', 'open')
                ->exists();

            if (!$exists) {
                DB::table('incident_tickets')->insert([
                    'order_id' => $stuck->order_id,
                    'payment_intent_id' => $stuck->payment_intent_id,
                    'type' => 'paid_not_settled',
                    'status' => 'open',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::warning('reconciliation', [
                    'type' => 'paid_not_settled',
                    'order_id' => $stuck->order_id,
                    'payment_intent_id' => $stuck->payment_intent_id,
                ]);

                // Create admin alert for dashboard visibility
                DB::table('admin_alerts')->insert([
                    'type' => 'paid_not_settled',
                    'severity' => 'warning',
                    'message' => "Order #{$stuck->order_id} has confirmed payment but is not settled.",
                    'threshold_value' => null,
                    'actual_value' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->line("  Created ticket + alert for order #{$stuck->order_id}");
            }
        }

        // Find expired payment intents that are still pending
        $expired = DB::table('payment_intents')
            ->where('status', 'pending')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $intent) {
            DB::table('payment_intents')
                ->where('id', $intent->id)
                ->update(['status' => 'failed', 'updated_at' => now()]);

            $this->line("  Expired intent #{$intent->id} for order #{$intent->order_id}");
        }

        $this->info('Reconciliation complete.');
        return self::SUCCESS;
    }
}
