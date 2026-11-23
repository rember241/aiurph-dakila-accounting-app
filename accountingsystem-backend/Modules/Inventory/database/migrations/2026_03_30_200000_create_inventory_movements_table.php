<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->foreignUuid('sales_invoice_id')->nullable()->constrained('sales_invoices')->nullOnDelete();
            $table->foreignUuid('sales_invoice_line_id')->nullable()->constrained('sales_invoice_lines')->nullOnDelete();
            $table->string('movement_type', 40);
            $table->string('direction', 16);
            $table->decimal('quantity', 15, 2);
            $table->decimal('quantity_before', 15, 2);
            $table->decimal('quantity_after', 15, 2);
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->string('reference_number', 40)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['company_id', 'inventory_item_id', 'occurred_at'], 'inventory_movements_company_item_occurred_index');
            $table->index(['company_id', 'sales_invoice_id'], 'inventory_movements_company_invoice_index');
            $table->index(['company_id', 'movement_type', 'direction'], 'inventory_movements_company_type_direction_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
