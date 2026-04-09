<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_blacklists', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // device, ip, username
            $table->string('value');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'value']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('security_whitelists', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // device, ip, username
            $table->string('value');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['type', 'value']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('rule_hit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50);
            $table->unsignedBigInteger('device_fingerprint_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->jsonb('details')->nullable();
            $table->timestamp('created_at');

            $table->index('type');
            $table->index('created_at');
            $table->foreign('device_fingerprint_id')->references('id')->on('device_fingerprints')->nullOnDelete();
        });

        Schema::create('privilege_escalation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 100);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('manager_id');
            $table->string('manager_pin_hash')->nullable();
            $table->text('reason')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index('action');
            $table->index('created_at');
            $table->foreign('manager_id')->references('id')->on('users');
        });

        // PostgreSQL triggers to prevent modification of audit logs
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('
                CREATE TRIGGER no_modify_rule_hit_logs
                    BEFORE UPDATE OR DELETE ON rule_hit_logs
                    FOR EACH ROW EXECUTE FUNCTION prevent_modification();
            ');

            DB::unprepared('
                CREATE TRIGGER no_modify_privilege_escalation_logs
                    BEFORE UPDATE OR DELETE ON privilege_escalation_logs
                    FOR EACH ROW EXECUTE FUNCTION prevent_modification();
            ');
        }
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS no_modify_privilege_escalation_logs ON privilege_escalation_logs');
        DB::unprepared('DROP TRIGGER IF EXISTS no_modify_rule_hit_logs ON rule_hit_logs');
        Schema::dropIfExists('privilege_escalation_logs');
        Schema::dropIfExists('rule_hit_logs');
        Schema::dropIfExists('security_whitelists');
        Schema::dropIfExists('security_blacklists');
    }
};
