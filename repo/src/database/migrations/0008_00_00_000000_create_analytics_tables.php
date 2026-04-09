<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // For PostgreSQL, create partitioned table; for SQLite, create regular table
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared("
                CREATE TABLE analytics_events (
                    id BIGSERIAL,
                    event_type VARCHAR(50) NOT NULL,
                    device_fingerprint_id BIGINT,
                    session_id VARCHAR(255),
                    payload JSONB,
                    trace_id UUID NOT NULL DEFAULT gen_random_uuid(),
                    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                    PRIMARY KEY (id, created_at)
                ) PARTITION BY RANGE (created_at);
            ");

            // Create partitions for current and next month
            $current = now()->format('Y_m');
            $currentStart = now()->startOfMonth()->format('Y-m-d');
            $nextStart = now()->addMonth()->startOfMonth()->format('Y-m-d');
            $nextNextStart = now()->addMonths(2)->startOfMonth()->format('Y-m-d');
            $nextMonth = now()->addMonth()->format('Y_m');

            DB::unprepared("
                CREATE TABLE analytics_events_{$current} PARTITION OF analytics_events
                    FOR VALUES FROM ('{$currentStart}') TO ('{$nextStart}');
            ");
            DB::unprepared("
                CREATE TABLE analytics_events_{$nextMonth} PARTITION OF analytics_events
                    FOR VALUES FROM ('{$nextStart}') TO ('{$nextNextStart}');
            ");

            DB::unprepared("CREATE INDEX idx_analytics_events_type ON analytics_events (event_type, created_at);");
            DB::unprepared("CREATE INDEX idx_analytics_events_session ON analytics_events (session_id, created_at);");
        } else {
            Schema::create('analytics_events', function (Blueprint $table) {
                $table->id();
                $table->string('event_type', 50);
                $table->unsignedBigInteger('device_fingerprint_id')->nullable();
                $table->string('session_id', 255)->nullable();
                $table->json('payload')->nullable();
                $table->uuid('trace_id');
                $table->timestamp('created_at');
            });
        }

        Schema::create('admin_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->string('severity', 20); // critical, warning, info
            $table->text('message');
            $table->decimal('threshold_value', 10, 4)->nullable();
            $table->decimal('actual_value', 10, 4)->nullable();
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->foreign('acknowledged_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['severity', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_alerts');
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TABLE IF EXISTS analytics_events CASCADE');
        } else {
            Schema::dropIfExists('analytics_events');
        }
    }
};
