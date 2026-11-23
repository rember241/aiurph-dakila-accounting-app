<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('module', 50)->index();
            $table->string('document_type', 100)->index();
            $table->string('prefix', 20)->nullable();
            $table->string('separator', 5)->default('-');
            $table->unsignedTinyInteger('padding')->default(6);
            $table->unsignedBigInteger('current_number')->default(0);
            $table->enum('reset_policy', ['never', 'fiscal_year', 'period'])->default('fiscal_year');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();

            $table->unique(['module', 'document_type'], 'document_sequences_module_doc_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};

