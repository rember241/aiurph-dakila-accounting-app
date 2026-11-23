<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (!Schema::hasColumn('sales_invoices', 'transaction_series_id')) {
                $table->foreignUuid('transaction_series_id')
                    ->nullable()
                    ->after('customer_id')
                    ->constrained('journal_numbers')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('sales_invoices', 'journal_entry_id')) {
                $table->foreignUuid('journal_entry_id')
                    ->nullable()
                    ->after('total_amount')
                    ->constrained('journal_entries')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('sales_invoices', 'accounting_posted_at')) {
                $table->timestamp('accounting_posted_at')
                    ->nullable()
                    ->after('journal_entry_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoices', 'accounting_posted_at')) {
                $table->dropColumn('accounting_posted_at');
            }

            if (Schema::hasColumn('sales_invoices', 'journal_entry_id')) {
                $table->dropConstrainedForeignId('journal_entry_id');
            }

            if (Schema::hasColumn('sales_invoices', 'transaction_series_id')) {
                $table->dropConstrainedForeignId('transaction_series_id');
            }
        });
    }
};
