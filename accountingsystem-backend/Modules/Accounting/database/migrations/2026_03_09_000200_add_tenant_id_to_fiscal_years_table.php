<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->cascadeOnDelete();
        });

        $singleTenantId = DB::table('tenants')->pluck('id');
        if ($singleTenantId->count() === 1) {
            DB::table('fiscal_years')
                ->whereNull('tenant_id')
                ->update([
                    'tenant_id' => (string)$singleTenantId->first(),
                ]);
        }

        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->dropUnique('fiscal_years_code_unique');
            $table->dropUnique('fiscal_years_range_unique');

            $table->unique(['tenant_id', 'code'], 'fiscal_years_tenant_code_unique');
            $table->unique(['tenant_id', 'start_date', 'end_date'], 'fiscal_years_tenant_range_unique');
            $table->index(['tenant_id', 'status'], 'fiscal_years_tenant_status_index');
            $table->index(['tenant_id', 'is_current'], 'fiscal_years_tenant_current_index');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->dropUnique('fiscal_years_tenant_code_unique');
            $table->dropUnique('fiscal_years_tenant_range_unique');
            $table->dropIndex('fiscal_years_tenant_status_index');
            $table->dropIndex('fiscal_years_tenant_current_index');

            $table->unique('code', 'fiscal_years_code_unique');
            $table->unique(['start_date', 'end_date'], 'fiscal_years_range_unique');

            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
