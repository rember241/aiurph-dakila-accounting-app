<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `payment_channel` to sales_invoice_payments to capture the specific
 * bank or digital-wallet provider chosen on the payment form (e.g. "BDO
 * Unibank, Inc." or "GCash"). The value comes from the front-end
 * TypeScript allow-lists in
 * `features/(protected)/sales/payments/model/payment-channels.ts` and
 * stays optional — it's purely a label for human reporting and exports.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales_invoice_payments', function (Blueprint $table): void {
            $table->string('payment_channel', 120)->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoice_payments', function (Blueprint $table): void {
            $table->dropColumn('payment_channel');
        });
    }
};
