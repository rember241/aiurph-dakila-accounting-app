<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds company-scoped default receipt accounts used to resolve the debit side
 * of a sales invoice payment posting. One column per supported payment method
 * so the posting resolver does not have to guess by account name.
 *
 * Missing configuration results in a clear validation error when recording
 * a payment — we never fall back to a label-based lookup.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (!Schema::hasColumn('tenants', 'default_cash_receipt_account_id')) {
                $table->uuid('default_cash_receipt_account_id')
                    ->nullable()
                    ->after('default_sales_tax_payable_account_id');
                $table->foreign('default_cash_receipt_account_id')
                    ->references('id')->on('accounts')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('tenants', 'default_check_receipt_account_id')) {
                $table->uuid('default_check_receipt_account_id')
                    ->nullable()
                    ->after('default_cash_receipt_account_id');
                $table->foreign('default_check_receipt_account_id')
                    ->references('id')->on('accounts')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('tenants', 'default_bank_receipt_account_id')) {
                $table->uuid('default_bank_receipt_account_id')
                    ->nullable()
                    ->after('default_check_receipt_account_id');
                $table->foreign('default_bank_receipt_account_id')
                    ->references('id')->on('accounts')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('tenants', 'default_online_receipt_account_id')) {
                $table->uuid('default_online_receipt_account_id')
                    ->nullable()
                    ->after('default_bank_receipt_account_id');
                $table->foreign('default_online_receipt_account_id')
                    ->references('id')->on('accounts')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            foreach (
                [
                    'default_online_receipt_account_id',
                    'default_bank_receipt_account_id',
                    'default_check_receipt_account_id',
                    'default_cash_receipt_account_id',
                ] as $column
            ) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropForeign([$column]);
                    $table->dropColumn($column);
                }
            }
        });
    }
};
