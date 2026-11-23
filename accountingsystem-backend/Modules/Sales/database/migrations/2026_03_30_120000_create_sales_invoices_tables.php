<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('invoice_number', 40);
            $table->string('status', 24)->default('draft');
            $table->date('invoice_date');
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'invoice_number'], 'sales_invoices_company_invoice_number_unique');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'invoice_date']);
            $table->index(['company_id', 'customer_id']);
        });

        Schema::create('sales_invoice_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('inventory_item_id')->constrained('inventory_items')->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('item_code_snapshot', 64);
            $table->string('item_name_snapshot', 160);
            $table->text('item_description_snapshot')->nullable();
            $table->string('unit_of_measure_snapshot', 24);
            $table->string('item_type_snapshot', 40);
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('line_subtotal', 15, 2);
            $table->timestamps();

            $table->unique(['sales_invoice_id', 'line_number'], 'sales_invoice_lines_invoice_line_number_unique');
            $table->index(['company_id', 'sales_invoice_id']);
            $table->index(['company_id', 'inventory_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_lines');
        Schema::dropIfExists('sales_invoices');
    }
};
