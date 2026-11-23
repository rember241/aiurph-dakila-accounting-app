<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (!Schema::hasColumn('sales_invoices', 'due_date')) {
                $table->date('due_date')->nullable()->after('invoice_date');
                $table->index(['company_id', 'due_date'], 'sales_invoices_company_due_date_index');
            }

            if (!Schema::hasColumn('sales_invoices', 'issued_at')) {
                $table->timestamp('issued_at')->nullable()->after('due_date');
                $table->index(['company_id', 'issued_at'], 'sales_invoices_company_issued_at_index');
            }

            if (!Schema::hasColumn('sales_invoices', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('issued_at');
            }

            if (!Schema::hasColumn('sales_invoices', 'cancelled_reason')) {
                $table->text('cancelled_reason')->nullable()->after('cancelled_at');
            }
        });

        if (Schema::hasColumn('sales_invoices', 'posted_at')) {
            DB::table('sales_invoices')
                ->whereNull('issued_at')
                ->whereNotNull('posted_at')
                ->update([
                    'issued_at' => DB::raw('posted_at'),
                ]);
        }

        DB::table('sales_invoices')
            ->where('status', 'posted')
            ->update([
                'status' => 'issued',
            ]);
    }

    public function down(): void
    {
        DB::table('sales_invoices')
            ->where('status', 'issued')
            ->whereNotNull('issued_at')
            ->update([
                'status' => 'posted',
            ]);

        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoices', 'cancelled_reason')) {
                $table->dropColumn('cancelled_reason');
            }

            if (Schema::hasColumn('sales_invoices', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }

            if (Schema::hasColumn('sales_invoices', 'issued_at')) {
                $table->dropIndex('sales_invoices_company_issued_at_index');
                $table->dropColumn('issued_at');
            }

            if (Schema::hasColumn('sales_invoices', 'due_date')) {
                $table->dropIndex('sales_invoices_company_due_date_index');
                $table->dropColumn('due_date');
            }
        });
    }
};
