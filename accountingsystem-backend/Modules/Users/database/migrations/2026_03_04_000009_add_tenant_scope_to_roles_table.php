<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Phase 1 foundation: make roles tenant-aware.
     *
     * Notes:
     * - `tenant_id` is nullable to keep existing global roles valid during migration.
     * - Unique key changes from `key` to composite `(tenant_id, key)`.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->cascadeOnDelete();
        });

        // Drop old unique(key) created before tenant scoping.
        // PostgreSQL manages this as a constraint, not a plain index.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE roles DROP CONSTRAINT IF EXISTS roles_key_unique');
        } else {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropUnique('roles_key_unique');
            });
        }

        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['tenant_id', 'key'], 'roles_tenant_key_unique');
            $table->index(['tenant_id', 'status'], 'roles_tenant_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_tenant_key_unique');
            $table->dropIndex('roles_tenant_status_index');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->unique('key', 'roles_key_unique');
        });
    }
};
