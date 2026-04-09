<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->foreignId('menu_category_id')->constrained('menu_categories')->cascadeOnDelete();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('tax_category', 50)->default('hot_prepared');
            $table->boolean('is_active')->default(true);
            $table->jsonb('attributes')->default('{}');
            $table->timestamps();

            $table->index('is_active');
            $table->index('menu_category_id');
            $table->index('price');
        });

        // PostgreSQL-specific: full-text search and GIN indexes
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("
                ALTER TABLE menu_items
                ADD COLUMN search_vector tsvector
                GENERATED ALWAYS AS (
                    to_tsvector('english', coalesce(name, '') || ' ' || coalesce(description, ''))
                ) STORED;
            ");

            DB::unprepared("
                CREATE INDEX idx_menu_items_search ON menu_items USING GIN(search_vector);
            ");

            DB::unprepared("
                CREATE INDEX idx_menu_items_attributes ON menu_items USING GIN(attributes);
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
