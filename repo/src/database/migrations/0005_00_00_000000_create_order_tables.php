<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 20)->unique();
            $table->foreignId('cart_id')->constrained('carts');
            $table->string('status', 30)->default('pending_confirmation');
            $table->integer('version')->default(1);
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->unsignedBigInteger('prepared_by')->nullable();
            $table->unsignedBigInteger('served_by')->nullable();
            $table->unsignedBigInteger('settled_by')->nullable();
            $table->unsignedBigInteger('canceled_by')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('prepared_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('served_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('settled_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('canceled_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('menu_item_id');
            $table->string('item_name', 255);
            $table->string('item_sku', 50);
            $table->decimal('unit_price', 10, 2);
            $table->integer('quantity');
            $table->string('tax_category', 50);
            $table->string('flavor_preference', 255)->nullable();
            $table->string('note', 140)->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->foreign('menu_item_id')->references('id')->on('menu_items');
        });

        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status', 30);
            $table->string('to_status', 30);
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->integer('version_at_change');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['order_id', 'created_at']);
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
        });

        // PostgreSQL trigger for immutable order status logs
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('
                CREATE TRIGGER no_modify_order_status_logs
                    BEFORE UPDATE OR DELETE ON order_status_logs
                    FOR EACH ROW EXECUTE FUNCTION prevent_modification();
            ');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS no_modify_order_status_logs ON order_status_logs');
        }
        Schema::dropIfExists('order_status_logs');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
