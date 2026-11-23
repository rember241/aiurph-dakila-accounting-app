<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Consolidate the per-provider digital-wallet rows (1220–1224, named GCash /
 * Maya / GrabPay / ShopeePay / Coins.ph) into a single generic posting
 * account "Digital Wallet" (code 1230). The specific wallet provider is now
 * stored per-payment in `sales_invoice_payments.payment_channel`.
 *
 * Steps, per company:
 *   1. Delete any system-flagged rows at codes 1220–1224 whose names match
 *      the original wallet seed exactly. User-renamed or user-created rows
 *      are left untouched.
 *   2. Insert (idempotently) the canonical 1230 — Digital Wallet row under
 *      the existing 1200 — Bank Accounts parent.
 */
return new class extends Migration {
    /** @var list<array{code: string, name: string}> */
    private const LEGACY_WALLET_ACCOUNTS = [
        ['code' => '1220', 'name' => 'GCash'],
        ['code' => '1221', 'name' => 'Maya'],
        ['code' => '1222', 'name' => 'GrabPay'],
        ['code' => '1223', 'name' => 'ShopeePay'],
        ['code' => '1224', 'name' => 'Coins.ph'],
    ];

    public function up(): void
    {
        // ─── Step 1: remove the 5 legacy per-provider system rows ────────
        foreach (self::LEGACY_WALLET_ACCOUNTS as $legacy) {
            DB::table('accounts')
                ->where('code', $legacy['code'])
                ->where('name', $legacy['name'])
                ->where('is_system', true)
                ->delete();
        }

        // ─── Step 2: insert the consolidated 1230 — Digital Wallet ───────
        $currentAssetsTypeId = DB::table('account_types')
            ->where('name', 'Current Assets')
            ->value('id');

        $bankAccountsDetailTypeId = $currentAssetsTypeId
            ? DB::table('detail_types')
                ->where('account_type_id', $currentAssetsTypeId)
                ->where('name', 'Bank Accounts')
                ->value('id')
            : null;

        if (!$currentAssetsTypeId || !$bankAccountsDetailTypeId) {
            // Lookup tables aren't seeded yet — fresh-install seeders will
            // create the row from CanonicalChartOfAccountsTemplate instead.
            return;
        }

        $parents = DB::table('accounts')
            ->where('code', '1200')
            ->whereNotNull('company_id')
            ->get(['id', 'company_id', 'tenant_id']);

        $now = now();

        foreach ($parents as $parent) {
            // Skip if 1230 is already present (any name) for this company.
            $codeTaken = DB::table('accounts')
                ->where('company_id', $parent->company_id)
                ->where('code', '1230')
                ->exists();

            if ($codeTaken) {
                continue;
            }

            // Skip if a row already named "Digital Wallet" sits under 1200.
            $nameTaken = DB::table('accounts')
                ->where('company_id', $parent->company_id)
                ->where('parent_id', $parent->id)
                ->whereRaw('LOWER(name) = ?', ['digital wallet'])
                ->exists();

            if ($nameTaken) {
                continue;
            }

            DB::table('accounts')->insert([
                'id' => (string) Str::uuid(),
                'company_id' => $parent->company_id,
                'tenant_id' => $parent->tenant_id ?? $parent->company_id,
                'code' => '1230',
                'name' => 'Digital Wallet',
                'type' => 'asset',
                'normal_balance' => 'debit',
                'parent_id' => $parent->id,
                'is_posting' => true,
                'is_contra' => false,
                'status' => 'active',
                'account_type_id' => $currentAssetsTypeId,
                'detail_type_id' => $bankAccountsDetailTypeId,
                'is_system' => true,
                'description' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Reverse only system-flagged 1230 rows we created.
        DB::table('accounts')
            ->where('code', '1230')
            ->where('name', 'Digital Wallet')
            ->where('is_system', true)
            ->delete();
    }
};
