<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('company_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('last_sequence')->default(0);
            $table->timestamps();

            $table->unique('company_id', 'customer_sequences_company_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_sequences');
    }
};
