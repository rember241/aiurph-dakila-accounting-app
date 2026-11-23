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
        Schema::create('fund_sources', function (Blueprint $table) {
            // Internal database ID
            $table->uuid('id')->primary();

            $table->string('name')->index();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('is_used')->default(false); // Flag to tell if the fund source has already been used in entries

            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fund_sources');
    }
};
