<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Encrypted note payloads far exceed 140 chars.
     * Expand to TEXT to prevent truncation / data corruption.
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->text('note')->nullable()->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->text('note')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->string('note', 140)->nullable()->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('note', 140)->nullable()->change();
        });
    }
};
