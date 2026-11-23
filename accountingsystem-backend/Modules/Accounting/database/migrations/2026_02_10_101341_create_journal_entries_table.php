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
        Schema::create('journal_entries', function (Blueprint $table) {
            // Internal database ID
            $table->uuid('id')->primary();

            $table->string('prefix');
            $table->integer('entry_number');
            $table->date('entry_date');
            $table->enum('type', ['general', 'adjusting', 'closing'])->default('general')->index();
            $table->foreignUuid('fund_source_id')->constrained('fund_sources');
            $table->string('reference_number')->nullable();
            $table->string('notes', 500)->nullable();

            // Amounts can contain up to 14 digits including the decimal numbers
            // e.g., 999,999,999,999.99
            $table->decimal('amount', 14);

            $table->enum('status', ['posted', 'draft', 'void'])->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
