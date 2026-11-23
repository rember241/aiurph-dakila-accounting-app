<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (!Schema::hasColumn('tenants', 'invoice_header_title')) {
                $table->string('invoice_header_title', 160)->nullable()->after('address');
            }

            if (!Schema::hasColumn('tenants', 'invoice_footer_note')) {
                $table->text('invoice_footer_note')->nullable()->after('invoice_header_title');
            }

            if (!Schema::hasColumn('tenants', 'invoice_payment_note')) {
                $table->text('invoice_payment_note')->nullable()->after('invoice_footer_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (Schema::hasColumn('tenants', 'invoice_payment_note')) {
                $table->dropColumn('invoice_payment_note');
            }

            if (Schema::hasColumn('tenants', 'invoice_footer_note')) {
                $table->dropColumn('invoice_footer_note');
            }

            if (Schema::hasColumn('tenants', 'invoice_header_title')) {
                $table->dropColumn('invoice_header_title');
            }
        });
    }
};
