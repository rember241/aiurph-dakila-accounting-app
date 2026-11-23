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
        Schema::create('taxes', function (Blueprint $table) {
            // Internal database ID
            $table->uuid('id')->primary();

            $table->string('name');
            $table->decimal('rate', 5);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('is_used')->default(false); // Flag to tell if the tax has already been used in entries

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('taxes');
    }
};
