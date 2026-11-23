<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Backfill a default posting "Bank Account" (code 1210) under the existing
 * non-posting "Bank Accounts" parent (code 1200) for every company that's
 * already been onboarded.
 *
 * Newly-onboarded companies get this row automatically through
 * CanonicalChartOfAccountsTemplate; this migration is purely for companies
 * that were bootstrapped before the template included it.
 *
 * Idempotent: if a posting bank account already exists under 1200 (any code,
 * any name) for a company, we leave it alone.
 */
return new class extends Migration {
    public function up(): void
    {
        // Resolve the global account-type / detail-type IDs once. Both tables
        // are seeded at bootstrap and shared across companies.
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
            // The lookup tables haven't been seeded yet. This is normal during
            // a fresh-install migration sequence — the AccountSeeder will
            // create the canonical bank account itself when it runs, so we
            // skip silently.
            return;
        }

        // Find every parent "Bank Accounts" row (code 1200) across companies.
        $parents = DB::table('accounts')
            ->where('code', '1200')
            ->whereNotNull('company_id')
            ->get(['id', 'company_id', 'tenant_id']);

        foreach ($parents as $parent) {
            // Skip if this company already has a posting bank account under
            // the 1200 parent (regardless of code or name).
            $alreadyHasPostingBank = DB::table('accounts')
                ->where('company_id', $parent->company_id)
                ->where('parent_id', $parent->id)
                ->where('is_posting', true)
                ->exists();

            if ($alreadyHasPostingBank) {
                continue;
            }

            // Skip if a row with code 1210 already exists for this company —
            // the unique (company_id, code) index would reject the insert.
            $codeTaken = DB::table('accounts')
                ->where('company_id', $parent->company_id)
                ->where('code', '1210')
                ->exists();

            if ($codeTaken) {
                continue;
            }

            DB::table('accounts')->insert([
                'id' => (string) Str::uuid(),
                'company_id' => $parent->company_id,
                'tenant_id' => $parent->tenant_id ?? $parent->company_id,
                'code' => '1210',
                'name' => 'Bank Account',
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
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Reverse only the rows we created — system-flagged 1210 rows under
        // a 1200 parent. We won't delete user-created bank accounts.
        DB::table('accounts')
            ->where('code', '1210')
            ->where('is_system', true)
            ->where('name', 'Bank Account')
            ->delete();
    }
};
