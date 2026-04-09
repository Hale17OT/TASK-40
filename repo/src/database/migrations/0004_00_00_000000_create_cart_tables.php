<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 255)->index();
            $table->unsignedBigInteger('device_fingerprint_id')->nullable();
            $table->timestamps();

            $table->foreign('device_fingerprint_id')->references('id')->on('device_fingerprints')->nullOnDelete();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained('menu_items');
            $table->integer('quantity')->default(1);
            $table->string('flavor_preference', 255)->nullable();
            $table->string('note', 140)->nullable();
            $table->decimal('unit_price_snapshot', 10, 2);
            $table->timestamps();

            $table->unique(['cart_id', 'menu_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
