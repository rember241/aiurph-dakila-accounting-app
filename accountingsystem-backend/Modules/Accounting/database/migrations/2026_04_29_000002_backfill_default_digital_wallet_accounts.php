<?php

use Illuminate\Database\Migrations\Migration;

/**
 * SUPERSEDED.
 *
 * The original version of this migration created five posting accounts
 * (GCash, Maya, GrabPay, ShopeePay, Coins.ph) under the Bank Accounts
 * parent. We've since consolidated to a single "Digital Wallet" posting
 * account — the specific wallet provider is now captured per-payment in
 * `sales_invoice_payments.payment_channel`.
 *
 * If you ran the original version, the cleanup is in
 * 2026_04_29_000003_consolidate_digital_wallet_account.php which removes
 * the five rows and inserts the single replacement.
 *
 * Kept as a no-op so Laravel can still resolve the migration class for
 * fresh installs (the migrations table only stores the filename, so we
 * can't delete the file once it's in any team member's history).
 */
return new class extends Migration {
    public function up(): void
    {
        // intentionally empty — see migration 000003 for the replacement
    }

    public function down(): void
    {
        // intentionally empty
    }
};
