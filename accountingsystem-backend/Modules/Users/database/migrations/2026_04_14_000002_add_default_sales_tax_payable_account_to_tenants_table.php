<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (!Schema::hasColumn('tenants', 'default_sales_tax_payable_account_id')) {
                $table->uuid('default_sales_tax_payable_account_id')
                    ->nullable()
                    ->after('default_cogs_account_id');

                $table->foreign('default_sales_tax_payable_account_id')
                    ->references('id')
                    ->on('accounts')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (Schema::hasColumn('tenants', 'default_sales_tax_payable_account_id')) {
                $table->dropForeign(['default_sales_tax_payable_account_id']);
                $table->dropColumn('default_sales_tax_payable_account_id');
            }
        });
    }
};
