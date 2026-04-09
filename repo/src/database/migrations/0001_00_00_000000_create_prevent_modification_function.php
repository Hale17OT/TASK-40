<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Create the prevent_modification() PG function used by immutable audit log triggers.
     * This migration must run before any migration that references it.
     * Idempotent: uses CREATE OR REPLACE.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return; // Only needed for PostgreSQL
        }

        DB::unprepared("
            CREATE OR REPLACE FUNCTION prevent_modification()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'Modification of audit log records is prohibited';
                RETURN NULL;
            END;
            \$\$ LANGUAGE plpgsql;
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP FUNCTION IF EXISTS prevent_modification();');
    }
};
