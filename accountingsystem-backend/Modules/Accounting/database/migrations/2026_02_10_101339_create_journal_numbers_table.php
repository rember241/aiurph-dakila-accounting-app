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
        Schema::create('journal_numbers', function (Blueprint $table) {
            // Internal database ID
            $table->uuid('id')->primary();

            $table->string('name');
            $table->string('prefix'); // Should be unique, but uniqueness is currently enforced in validation rules only. Refactor if necessary
            $table->integer('starting_number')->default(1);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->enum('reset_interval', ['daily', 'weekly', 'monthly', 'yearly'])->default('daily');
            $table->boolean('is_used')->default(false); // Flag to determine if the journal number has been used in previous transactions

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_numbers');
    }
};
