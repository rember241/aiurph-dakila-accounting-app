<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->date('pricing_as_of')->nullable()->after('cost_price');
            $table->date('stock_as_of')->nullable()->after('pricing_as_of');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table): void {
            $table->dropColumn(['pricing_as_of', 'stock_as_of']);
        });
    }
};
