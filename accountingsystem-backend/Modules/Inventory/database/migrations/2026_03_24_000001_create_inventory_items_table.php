<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('code', 64);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('unit_of_measure', 24);
            $table->string('item_type', 32)->default('inventory');
            $table->string('category', 80)->nullable();
            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->unique(['company_id', 'code'], 'inventory_items_company_id_code_unique');
            $table->index(['company_id', 'status'], 'inventory_items_company_id_status_index');
            $table->index(['company_id', 'item_type'], 'inventory_items_company_id_item_type_index');
            $table->index(['company_id', 'name'], 'inventory_items_company_id_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
