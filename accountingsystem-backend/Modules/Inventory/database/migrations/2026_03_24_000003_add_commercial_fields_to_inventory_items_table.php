<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->decimal('sales_price', 18, 2)
                ->nullable()
                ->after('quantity_on_hand');
            $table->decimal('cost_price', 18, 2)
                ->nullable()
                ->after('sales_price');
            $table->unsignedInteger('reorder_point')
                ->nullable()
                ->after('cost_price');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropColumn(['sales_price', 'cost_price', 'reorder_point']);
        });
    }
};
