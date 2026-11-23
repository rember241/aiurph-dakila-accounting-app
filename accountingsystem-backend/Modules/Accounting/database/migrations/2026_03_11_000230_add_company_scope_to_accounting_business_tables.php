<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // TODO: Remove this guard if no dev/prod environment has this index
         $hasLegacyJournalEntryNumberUnique = Schema::hasIndex(
         'journal_entries',
         'journal_entries_entry_number_unique',
         'unique'
         );

        // Adds the company_id FK to the accounts table and creates indexes
        Schema::table('accounts', function (Blueprint $table): void {
            $table->foreignUuid('company_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('tenants')
                ->nullOnDelete();

            $table->index(['company_id', 'type', 'status'], 'accounts_company_type_status_idx');
            $table->index(['company_id', 'parent_id'], 'accounts_company_parent_idx');
        });

        // To accounts that don't have a company_id, copy the tenant_id to the said column
        DB::table('accounts')
            ->whereNull('company_id')
            ->whereNotNull('tenant_id')
            ->update([
                'company_id' => DB::raw('tenant_id'),
            ]);

        // Adds the company_id FK to the fiscal_years table and creates indexes
        Schema::table('fiscal_years', function (Blueprint $table): void {
            $table->foreignUuid('company_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('tenants')
                ->nullOnDelete();

            $table->index(['company_id', 'status'], 'fiscal_years_company_status_index');
            $table->index(['company_id', 'is_current'], 'fiscal_years_company_current_index');
        });

        // To fiscal years that don't have a company_id, copy the tenant_id to the said column
        DB::table('fiscal_years')
            ->whereNull('company_id')
            ->whereNotNull('tenant_id')
            ->update([
                'company_id' => DB::raw('tenant_id'),
            ]);

        // Adds the company_id FK to the accounting_periods table and creates indexes
        Schema::table('accounting_periods', function (Blueprint $table): void {
            $table->foreignUuid('company_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('tenants')
                ->nullOnDelete();

            $table->index(['company_id', 'status'], 'accounting_periods_company_status_idx');
            $table->index(['company_id', 'fiscal_year_id'], 'accounting_periods_company_fiscal_year_idx');
        });

        // To accounting periods that don't have a company_id, copy the tenant_id to the said column
        DB::table('accounting_periods')
            ->whereNull('company_id')
            ->whereNotNull('tenant_id')
            ->update([
                'company_id' => DB::raw('tenant_id'),
            ]);

        // Adds the company_id FK to the taxes table and creates index
        Schema::table('taxes', function (Blueprint $table): void {
            $table->foreignUuid('company_id')
                ->nullable()
                ->constrained('tenants')
                ->nullOnDelete();

            $table->index(['company_id', 'status'], 'taxes_company_status_idx');
        });

        // Adds the company_id FK to the fund_sources table and creates index
        Schema::table('fund_sources', function (Blueprint $table): void {
            $table->foreignUuid('company_id')
                ->nullable()
                ->constrained('tenants')
                ->nullOnDelete();

            $table->index(['company_id', 'status'], 'fund_sources_company_status_idx');
        });

        // Adds the company_id FK to the journal_numbers table and create unique key and indexes
        Schema::table('journal_numbers', function (Blueprint $table): void {
            $table->foreignUuid('company_id')
                ->nullable()
                ->constrained('tenants')
                ->nullOnDelete();

            $table->unique(['company_id', 'prefix'], 'journal_numbers_company_prefix_unique');
            $table->index(['company_id', 'status'], 'journal_numbers_company_status_idx');
            $table->index(['company_id', 'reset_interval'], 'journal_numbers_company_reset_interval_idx');
        });

        // Adds the company_id and org_unit_id FKs to the journal_entries table and creates indexes
        Schema::table('journal_entries', function (Blueprint $table) use ($hasLegacyJournalEntryNumberUnique): void {
            $table->foreignUuid('company_id')
                ->nullable()
                ->after('id')
                ->constrained('tenants')
                ->nullOnDelete();

            $table->foreignUuid('org_unit_id')
                ->nullable()
                ->after('company_id')
                ->constrained('org_units')
                ->nullOnDelete();

             if ($hasLegacyJournalEntryNumberUnique) {
             $table->dropUnique('journal_entries_entry_number_unique');
             }

            $table->index(['company_id', 'entry_date'], 'journal_entries_company_entry_date_index');
            $table->index(['company_id', 'org_unit_id'], 'journal_entries_company_org_unit_index');
        });

        // For each journal entry, figure out which company it belongs to
        // by looking at its related accounts, then save that company_id
        $journalEntries = DB::table('journal_entries')->select('id')->get();
        foreach ($journalEntries as $journalEntry) {
            // Get company_id from related accounts
            $companyId = DB::table('journal_items as ji')
                ->join('accounts as a', 'a.id', '=', 'ji.account_id')
                ->where('ji.journal_entry_id', $journalEntry->id)
                ->orderBy('ji.id')
                ->value('a.company_id');

            // Get tenant_id instead if company_id doesn't exist
            if ($companyId === null) {
                $companyId = DB::table('journal_items as ji')
                    ->join('accounts as a', 'a.id', '=', 'ji.account_id')
                    ->where('ji.journal_entry_id', $journalEntry->id)
                    ->orderBy('ji.id')
                    ->value('a.tenant_id');
            }

            // Update the company_id of each journal entry
            if ($companyId !== null) {
                DB::table('journal_entries')
                    ->where('id', $journalEntry->id)
                    ->update([
                        'company_id' => (string) $companyId,
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // TODO: Remove this guard if no dev/prod environment has this index
         $hasCompanyScopedJournalEntryNumberUnique = Schema::hasIndex(
         'journal_entries',
         'journal_entries_company_entry_number_unique',
         'unique'
         );

        Schema::table('journal_entries', function (Blueprint $table) use ($hasCompanyScopedJournalEntryNumberUnique): void {
            $table->dropIndex('journal_entries_company_entry_date_index');
            $table->dropIndex('journal_entries_company_org_unit_index');
             if ($hasCompanyScopedJournalEntryNumberUnique) {
             $table->dropUnique('journal_entries_company_entry_number_unique');
             }

            $table->dropForeign(['company_id']);
            $table->dropForeign(['org_unit_id']);
            $table->dropColumn(['company_id', 'org_unit_id']);
        });

        Schema::table('accounting_periods', function (Blueprint $table): void {
            $table->dropIndex('accounting_periods_company_status_idx');
            $table->dropIndex('accounting_periods_company_fiscal_year_idx');
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('fiscal_years', function (Blueprint $table): void {
            $table->dropIndex('fiscal_years_company_status_index');
            $table->dropIndex('fiscal_years_company_current_index');
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('taxes', function (Blueprint $table): void {
            DB::statement('DROP INDEX IF EXISTS taxes_company_status_idx');
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('fund_sources', function (Blueprint $table): void {
            DB::statement('DROP INDEX IF EXISTS fund_sources_company_status_idx');
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('journal_numbers', function (Blueprint $table): void {
            DB::statement('ALTER TABLE journal_numbers DROP CONSTRAINT IF EXISTS journal_numbers_company_prefix_unique');
            DB::statement('DROP INDEX IF EXISTS journal_numbers_company_status_idx');
            DB::statement('DROP INDEX IF EXISTS journal_numbers_company_reset_interval_idx');
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('accounts', function (Blueprint $table): void {
            $table->dropIndex('accounts_company_type_status_idx');
            $table->dropIndex('accounts_company_parent_idx');
            $table->dropForeign(['company_id']);
            $table->dropColumn('company_id');
        });
    }
};
