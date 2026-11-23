<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->uuid('default_accounts_receivable_account_id')->nullable()->after('timezone');
            $table->uuid('default_sales_income_account_id')->nullable()->after('default_accounts_receivable_account_id');
            $table->uuid('default_inventory_asset_account_id')->nullable()->after('default_sales_income_account_id');
            $table->uuid('default_cogs_account_id')->nullable()->after('default_inventory_asset_account_id');

            $table->foreign('default_accounts_receivable_account_id')
                ->references('id')->on('accounts')->nullOnDelete();

            $table->foreign('default_sales_income_account_id')
                ->references('id')->on('accounts')->nullOnDelete();

            $table->foreign('default_inventory_asset_account_id')
                ->references('id')->on('accounts')->nullOnDelete();

            $table->foreign('default_cogs_account_id')
                ->references('id')->on('accounts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {

            $table->dropForeign(['default_accounts_receivable_account_id']);
            $table->dropForeign(['default_sales_income_account_id']);
            $table->dropForeign(['default_inventory_asset_account_id']);
            $table->dropForeign(['default_cogs_account_id']);

            $table->dropColumn([
                'default_accounts_receivable_account_id',
                'default_sales_income_account_id',
                'default_inventory_asset_account_id',
                'default_cogs_account_id',
            ]);

        });
    }
};
