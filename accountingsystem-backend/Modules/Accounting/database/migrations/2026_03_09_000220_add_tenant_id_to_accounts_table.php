<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->cascadeOnDelete();
        });

        $singleTenantId = DB::table('tenants')->pluck('id');
        if ($singleTenantId->count() === 1) {
            DB::table('accounts')
                ->whereNull('tenant_id')
                ->update([
                    'tenant_id' => (string)$singleTenantId->first(),
                ]);
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique('accounts_code_unique');
            $table->dropIndex('accounts_type_status_index');
            $table->dropIndex('accounts_code_name_index');

            $table->unique(['tenant_id', 'code'], 'accounts_tenant_code_unique');
            $table->index(['tenant_id', 'type', 'status'], 'accounts_tenant_type_status_idx');
            $table->index(['tenant_id', 'parent_id'], 'accounts_tenant_parent_idx');
            $table->index(['tenant_id', 'code', 'name'], 'accounts_tenant_code_name_idx');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropUnique('accounts_tenant_code_unique');
            $table->dropIndex('accounts_tenant_type_status_idx');
            $table->dropIndex('accounts_tenant_parent_idx');
            $table->dropIndex('accounts_tenant_code_name_idx');

            $table->unique('code', 'accounts_code_unique');
            $table->index(['type', 'status'], 'accounts_type_status_index');
            $table->index(['code', 'name'], 'accounts_code_name_index');

            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
