<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trending_searches', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id')->nullable()->after('pinned_by');
            $table->index(['location_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('trending_searches', function (Blueprint $table) {
            $table->dropIndex(['location_id', 'sort_order']);
            $table->dropColumn('location_id');
        });
    }
};
