<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10);
            $table->string('path', 255);
            $table->unsignedSmallInteger('status_code');
            $table->float('duration_ms');
            $table->timestamp('created_at');

            $table->index(['created_at', 'status_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_metrics');
    }
};
