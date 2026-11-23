<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds accounting linkage and void metadata to sales_invoice_payments.
 *
 * Each recorded payment produces exactly one journal receipt entry. The
 * payment row stores a direct link to that journal entry so the frontend
 * can trace posting history, and carries a simple posting status so we
 * never silently lose history through destructive deletes. Voids post a
 * reversal entry rather than deleting the original row.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_invoice_payments', function (Blueprint $table): void {
            if (!Schema::hasColumn('sales_invoice_payments', 'journal_entry_id')) {
                $table->uuid('journal_entry_id')->nullable()->after('notes');
                $table->foreign('journal_entry_id')
                    ->references('id')->on('journal_entries')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('sales_invoice_payments', 'accounting_posted_at')) {
                $table->timestamp('accounting_posted_at')->nullable()->after('journal_entry_id');
            }

            if (!Schema::hasColumn('sales_invoice_payments', 'receipt_account_id')) {
                $table->uuid('receipt_account_id')->nullable()->after('payment_method');
                $table->foreign('receipt_account_id')
                    ->references('id')->on('accounts')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('sales_invoice_payments', 'voided_at')) {
                $table->timestamp('voided_at')->nullable()->after('accounting_posted_at');
            }

            if (!Schema::hasColumn('sales_invoice_payments', 'void_reason')) {
                $table->string('void_reason', 500)->nullable()->after('voided_at');
            }

            if (!Schema::hasColumn('sales_invoice_payments', 'reversal_journal_entry_id')) {
                $table->uuid('reversal_journal_entry_id')->nullable()->after('void_reason');
                $table->foreign('reversal_journal_entry_id')
                    ->references('id')->on('journal_entries')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoice_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoice_payments', 'reversal_journal_entry_id')) {
                $table->dropForeign(['reversal_journal_entry_id']);
                $table->dropColumn('reversal_journal_entry_id');
            }

            if (Schema::hasColumn('sales_invoice_payments', 'void_reason')) {
                $table->dropColumn('void_reason');
            }

            if (Schema::hasColumn('sales_invoice_payments', 'voided_at')) {
                $table->dropColumn('voided_at');
            }

            if (Schema::hasColumn('sales_invoice_payments', 'receipt_account_id')) {
                $table->dropForeign(['receipt_account_id']);
                $table->dropColumn('receipt_account_id');
            }

            if (Schema::hasColumn('sales_invoice_payments', 'accounting_posted_at')) {
                $table->dropColumn('accounting_posted_at');
            }

            if (Schema::hasColumn('sales_invoice_payments', 'journal_entry_id')) {
                $table->dropForeign(['journal_entry_id']);
                $table->dropColumn('journal_entry_id');
            }
        });
    }
};
