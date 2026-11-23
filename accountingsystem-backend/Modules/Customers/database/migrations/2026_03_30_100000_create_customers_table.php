<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('code', 64);
            $table->string('name', 160);
            $table->string('email', 160)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->foreign('company_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->unique(['company_id', 'code'], 'customers_company_id_code_unique');
            $table->index(['company_id', 'status'], 'customers_company_id_status_index');
            $table->index(['company_id', 'name'], 'customers_company_id_name_index');
            $table->index(['company_id', 'email'], 'customers_company_id_email_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
