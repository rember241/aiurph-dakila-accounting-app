<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (!Schema::hasColumn('sales_invoices', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0)->after('subtotal');
            }

            if (!Schema::hasColumn('sales_invoices', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->default(0)->after('tax_amount');
            }

            if (!Schema::hasColumn('sales_invoices', 'journal_entry_id')) {
                $table->foreignUuid('journal_entry_id')
                    ->nullable()
                    ->after('total_amount')
                    ->constrained('journal_entries')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('sales_invoices', 'reversal_journal_entry_id')) {
                $table->foreignUuid('reversal_journal_entry_id')
                    ->nullable()
                    ->after('journal_entry_id')
                    ->constrained('journal_entries')
                    ->nullOnDelete();
            }
        });

        DB::table('sales_invoices')->update([
            'tax_amount' => DB::raw('COALESCE(tax_amount, 0)'),
            'total_amount' => DB::raw('COALESCE(total_amount, subtotal + COALESCE(tax_amount, 0))'),
        ]);

        Schema::table('sales_invoice_lines', function (Blueprint $table): void {
            if (!Schema::hasColumn('sales_invoice_lines', 'tax_id')) {
                $table->foreignUuid('tax_id')
                    ->nullable()
                    ->after('item_type_snapshot')
                    ->constrained('taxes')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('sales_invoice_lines', 'tax_name_snapshot')) {
                $table->string('tax_name_snapshot', 160)->nullable()->after('tax_id');
            }

            if (!Schema::hasColumn('sales_invoice_lines', 'tax_rate_snapshot')) {
                $table->decimal('tax_rate_snapshot', 8, 4)->nullable()->after('tax_name_snapshot');
            }

            if (!Schema::hasColumn('sales_invoice_lines', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0)->after('line_subtotal');
            }

            if (!Schema::hasColumn('sales_invoice_lines', 'line_total')) {
                $table->decimal('line_total', 15, 2)->default(0)->after('tax_amount');
            }
        });

        DB::table('sales_invoice_lines')->update([
            'tax_amount' => DB::raw('COALESCE(tax_amount, 0)'),
            'line_total' => DB::raw('COALESCE(line_total, line_subtotal + COALESCE(tax_amount, 0))'),
        ]);
    }

    public function down(): void
    {
        Schema::table('sales_invoice_lines', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoice_lines', 'line_total')) {
                $table->dropColumn('line_total');
            }

            if (Schema::hasColumn('sales_invoice_lines', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }

            if (Schema::hasColumn('sales_invoice_lines', 'tax_rate_snapshot')) {
                $table->dropColumn('tax_rate_snapshot');
            }

            if (Schema::hasColumn('sales_invoice_lines', 'tax_name_snapshot')) {
                $table->dropColumn('tax_name_snapshot');
            }

            if (Schema::hasColumn('sales_invoice_lines', 'tax_id')) {
                $table->dropConstrainedForeignId('tax_id');
            }
        });

        Schema::table('sales_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('sales_invoices', 'reversal_journal_entry_id')) {
                $table->dropConstrainedForeignId('reversal_journal_entry_id');
            }

            if (Schema::hasColumn('sales_invoices', 'journal_entry_id')) {
                $table->dropConstrainedForeignId('journal_entry_id');
            }

            if (Schema::hasColumn('sales_invoices', 'total_amount')) {
                $table->dropColumn('total_amount');
            }

            if (Schema::hasColumn('sales_invoices', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }
        });
    }
};
