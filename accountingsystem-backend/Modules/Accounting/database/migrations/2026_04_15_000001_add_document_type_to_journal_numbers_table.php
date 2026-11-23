<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Enums\TransactionSeriesDocumentType;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('journal_numbers', function (Blueprint $table): void {
            if (!Schema::hasColumn('journal_numbers', 'document_type')) {
                $table->string('document_type', 40)
                    ->default(TransactionSeriesDocumentType::JOURNAL_ENTRY->value)
                    ->after('company_id');
            }
        });

        DB::table('journal_numbers')
            ->whereNull('document_type')
            ->update([
                'document_type' => TransactionSeriesDocumentType::JOURNAL_ENTRY->value,
            ]);

        Schema::table('journal_numbers', function (Blueprint $table) use ($driver): void {
            if ($driver === 'sqlite') {
                $table->dropUnique('journal_numbers_company_prefix_unique');
                $table->dropIndex('journal_numbers_company_status_idx');
                $table->dropIndex('journal_numbers_company_reset_interval_idx');
            } else {
                DB::statement('ALTER TABLE journal_numbers DROP CONSTRAINT IF EXISTS journal_numbers_company_prefix_unique');
                DB::statement('DROP INDEX IF EXISTS journal_numbers_company_status_idx');
                DB::statement('DROP INDEX IF EXISTS journal_numbers_company_reset_interval_idx');
            }

            $table->unique(['company_id', 'document_type', 'prefix'], 'journal_numbers_company_document_prefix_unique');
            $table->index(['company_id', 'document_type', 'status'], 'journal_numbers_company_document_status_idx');
            $table->index(['company_id', 'document_type', 'reset_interval'], 'journal_numbers_company_document_reset_interval_idx');
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('journal_numbers', function (Blueprint $table) use ($driver): void {
            if ($driver === 'sqlite') {
                $table->dropUnique('journal_numbers_company_document_prefix_unique');
                $table->dropIndex('journal_numbers_company_document_status_idx');
                $table->dropIndex('journal_numbers_company_document_reset_interval_idx');
            } else {
                DB::statement('ALTER TABLE journal_numbers DROP CONSTRAINT IF EXISTS journal_numbers_company_document_prefix_unique');
                DB::statement('DROP INDEX IF EXISTS journal_numbers_company_document_status_idx');
                DB::statement('DROP INDEX IF EXISTS journal_numbers_company_document_reset_interval_idx');
            }

            $table->unique(['company_id', 'prefix'], 'journal_numbers_company_prefix_unique');
            $table->index(['company_id', 'status'], 'journal_numbers_company_status_idx');
            $table->index(['company_id', 'reset_interval'], 'journal_numbers_company_reset_interval_idx');

            if (Schema::hasColumn('journal_numbers', 'document_type')) {
                $table->dropColumn('document_type');
            }
        });
    }
};
