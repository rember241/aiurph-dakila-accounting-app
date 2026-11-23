<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->foreignUuid('tenant_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->cascadeOnDelete();
        });

        DB::table('accounting_periods')
            ->select(['id', 'fiscal_year_id'])
            ->orderBy('id')
            ->chunk(100, function (Collection $periods): void {
                $tenantIdsByFiscalYear = DB::table('fiscal_years')
                    ->whereIn('id', $periods->pluck('fiscal_year_id')->filter()->unique()->values()->all())
                    ->pluck('tenant_id', 'id');

                foreach ($periods as $period) {
                    $tenantId = $tenantIdsByFiscalYear->get($period->fiscal_year_id);
                    if ($tenantId === null || $tenantId === '') {
                        continue;
                    }

                    DB::table('accounting_periods')
                        ->where('id', $period->id)
                        ->update([
                            'tenant_id' => (string)$tenantId,
                        ]);
                }
            });

        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->index(['tenant_id', 'status'], 'accounting_periods_tenant_status_idx');
            $table->index(['tenant_id', 'fiscal_year_id'], 'accounting_periods_tenant_fiscal_year_idx');
            $table->index(['tenant_id', 'start_date', 'end_date'], 'accounting_periods_tenant_range_idx');
        });
    }

    public function down(): void
    {
        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->dropIndex('accounting_periods_tenant_status_idx');
            $table->dropIndex('accounting_periods_tenant_fiscal_year_idx');
            $table->dropIndex('accounting_periods_tenant_range_idx');
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
