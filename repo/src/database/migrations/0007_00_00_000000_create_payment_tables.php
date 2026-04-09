<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->uuid('reference')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('hmac_signature', 128);
            $table->string('nonce', 64)->unique();
            $table->timestamp('expires_at');
            $table->string('status', 20)->default('pending'); // pending, confirmed, failed, canceled, reconciling
            $table->timestamps();

            $table->index('status');
            $table->index('order_id');
        });

        Schema::create('payment_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_intent_id')->constrained('payment_intents');
            $table->unsignedBigInteger('confirmed_by');
            $table->string('method', 30); // cash, card_manual
            $table->text('notes')->nullable(); // encrypted at rest via cast
            $table->timestamp('created_at');

            $table->foreign('confirmed_by')->references('id')->on('users');
        });

        Schema::create('incident_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders');
            $table->unsignedBigInteger('payment_intent_id')->nullable();
            $table->string('type', 50); // paid_not_settled, expired_intent, manual_review
            $table->string('status', 20)->default('open'); // open, resolved, dismissed
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->string('resolution_reason_code', 50)->nullable(); // cash_verified, card_receipt_matched, pos_system_confirmed, other
            $table->text('receipt_reference')->nullable(); // encrypted
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('payment_intent_id')->references('id')->on('payment_intents')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_tickets');
        Schema::dropIfExists('payment_confirmations');
        Schema::dropIfExists('payment_intents');
    }
};
