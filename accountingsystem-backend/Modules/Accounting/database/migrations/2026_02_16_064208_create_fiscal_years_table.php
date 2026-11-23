<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 20)->unique();
            $table->string('name', 50);
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->boolean('is_current')->default(false)->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['start_date', 'end_date'], 'fiscal_years_range_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_years');
    }
};

