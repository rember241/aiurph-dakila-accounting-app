<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('sales_invoices')) {
            return;
        }

        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (!Schema::hasColumn('sales_invoices', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0)->after('subtotal');
            }

            if (!Schema::hasColumn('sales_invoices', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->default(0)->after('tax_amount');
            }
        });

        // Back-fill total_amount for any existing rows
        DB::table('sales_invoices')->update([
            'total_amount' => DB::raw('subtotal + tax_amount'),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('sales_invoices')) {
            return;
        }

        Schema::table('sales_invoices', function (Blueprint $table): void {
            $columns = [];

            if (Schema::hasColumn('sales_invoices', 'total_amount')) {
                $columns[] = 'total_amount';
            }

            if (Schema::hasColumn('sales_invoices', 'tax_amount')) {
                $columns[] = 'tax_amount';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
