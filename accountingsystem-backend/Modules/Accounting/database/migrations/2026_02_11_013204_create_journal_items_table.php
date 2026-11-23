<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('journal_items', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // References journal_entries table
            $table->foreignUuid('journal_entry_id')
                ->constrained('journal_entries');

            // References accounts table
            $table->foreignUuid('account_id')
                ->constrained('accounts');

            // References taxes table
            $table->foreignUuid('tax_id')
                ->nullable()
                ->constrained('taxes');

            $table->string('description', 250)->nullable();
            $table->enum('type', ['debit', 'credit']);

            // Amounts can contain up to 14 digits including the decimal point and the numbers after it
            // e.g., 999,999,999,999.99
            $table->decimal('amount', 14);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_items');
    }
};
