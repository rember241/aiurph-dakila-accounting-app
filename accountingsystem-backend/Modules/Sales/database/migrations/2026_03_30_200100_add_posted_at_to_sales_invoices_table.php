<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (!Schema::hasColumn('sales_invoices', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('invoice_date');
                $table->index(['company_id', 'posted_at'], 'sales_invoices_company_posted_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoices', 'posted_at')) {
                $table->dropIndex('sales_invoices_company_posted_at_index');
                $table->dropColumn('posted_at');
            }
        });
    }
};
