<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            if (!Schema::hasColumn('inventory_movements', 'org_unit_id')) {
                $table->foreignUuid('org_unit_id')->nullable()->after('inventory_item_id')->constrained('org_units')->nullOnDelete();
            }

            if (!Schema::hasColumn('inventory_movements', 'source_type')) {
                $table->string('source_type', 60)->nullable()->after('sales_invoice_line_id');
            }

            if (!Schema::hasColumn('inventory_movements', 'source_id')) {
                $table->uuid('source_id')->nullable()->after('source_type');
            }

            if (!Schema::hasColumn('inventory_movements', 'movement_date')) {
                $table->date('movement_date')->nullable()->after('unit_cost');
            }
        });

        DB::table('inventory_movements')
            ->whereNull('source_type')
            ->update([
                'source_type' => DB::raw("CASE WHEN sales_invoice_id IS NOT NULL THEN 'sales_invoice' ELSE movement_type END"),
                'source_id' => DB::raw('COALESCE(sales_invoice_id, source_id)'),
                'movement_date' => DB::raw('DATE(occurred_at)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            if (Schema::hasColumn('inventory_movements', 'movement_date')) {
                $table->dropColumn('movement_date');
            }

            if (Schema::hasColumn('inventory_movements', 'source_id')) {
                $table->dropColumn('source_id');
            }

            if (Schema::hasColumn('inventory_movements', 'source_type')) {
                $table->dropColumn('source_type');
            }

            if (Schema::hasColumn('inventory_movements', 'org_unit_id')) {
                $table->dropConstrainedForeignId('org_unit_id');
            }
        });
    }
};
