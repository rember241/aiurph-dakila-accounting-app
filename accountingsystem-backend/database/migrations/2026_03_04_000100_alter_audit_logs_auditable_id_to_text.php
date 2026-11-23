<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Why this migration exists:
     * - `audit_logs.auditable_id` was created as a Postgres UUID column.
     * - Some models use UUID primary keys (e.g. Accounts), but others use integer keys (e.g. Users).
     * - Audit logs must be able to store both safely, so we store the identifier as text.
     */
    public function up(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }

        $driver = DB::getDriverName();

        // Postgres enforces UUID format; convert to text so we can store any model key type.
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE audit_logs ALTER COLUMN auditable_id TYPE text USING auditable_id::text");
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            return;
        }

        $driver = DB::getDriverName();

        // Best-effort rollback. This will fail if non-UUID values exist in the column.
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE audit_logs ALTER COLUMN auditable_id TYPE uuid USING auditable_id::uuid");
        }
    }
};

