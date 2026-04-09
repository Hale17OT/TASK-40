<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('type', 30); // percentage_off, flat_discount, bogo, percentage_off_second
            $table->json('rules'); // {"threshold": 30, "percentage": 10} or {"target_skus": ["SKU1"]}
            $table->string('exclusion_group', 50)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index(['starts_at', 'ends_at']);
        });

        Schema::create('applied_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('promotion_id')->constrained('promotions');
            $table->decimal('discount_amount', 10, 2);
            $table->text('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applied_promotions');
        Schema::dropIfExists('promotions');
    }
};
