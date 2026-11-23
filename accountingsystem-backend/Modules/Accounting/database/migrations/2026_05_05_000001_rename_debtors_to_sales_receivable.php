<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rename the canonical "Debtors" account (code 1310) to "Sales Receivable"
 * across all companies that still carry the old name.
 *
 * Newly-onboarded companies get the correct name from
 * CanonicalChartOfAccountsTemplate; this migration covers existing data.
 *
 * Idempotent: only rows where name is still "Debtors" are touched.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::table('accounts')
            ->where('code', '1310')
            ->where('name', 'Debtors')
            ->update([
                'name' => 'Sales Receivable',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('accounts')
            ->where('code', '1310')
            ->where('name', 'Sales Receivable')
            ->update([
                'name' => 'Debtors',
                'updated_at' => now(),
            ]);
    }
};
