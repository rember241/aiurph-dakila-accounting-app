<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounting_defaults', function (Blueprint $table) {
            $table->id();

            // Each column holds the UUID of the chosen default posting account.
            // All are nullable — no default is required until the company explicitly sets one.
            $table->uuid('accounts_receivable_account_id')->nullable();
            $table->uuid('sales_income_account_id')->nullable();
            $table->uuid('inventory_asset_account_id')->nullable();
            $table->uuid('cogs_account_id')->nullable();
            $table->uuid('sales_tax_payable_account_id')->nullable();

            $table->timestamps();

            $table->foreign('accounts_receivable_account_id')
                ->references('id')->on('accounts')->nullOnDelete();

            $table->foreign('sales_income_account_id')
                ->references('id')->on('accounts')->nullOnDelete();

            $table->foreign('inventory_asset_account_id')
                ->references('id')->on('accounts')->nullOnDelete();

            $table->foreign('cogs_account_id')
                ->references('id')->on('accounts')->nullOnDelete();

            $table->foreign('sales_tax_payable_account_id')
                ->references('id')->on('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_defaults');
    }
};
