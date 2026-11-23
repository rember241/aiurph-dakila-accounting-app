<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoice_lines', function (Blueprint $table): void {
            if (!Schema::hasColumn('sales_invoice_lines', 'unit_cost_snapshot')) {
                $table->decimal('unit_cost_snapshot', 15, 2)->nullable()->after('item_type_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoice_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoice_lines', 'unit_cost_snapshot')) {
                $table->dropColumn('unit_cost_snapshot');
            }
        });
    }
};
