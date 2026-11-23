<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Phase 1 foundation: map each tenant to its dedicated physical database.
     */
    public function up(): void
    {
        Schema::create('tenant_databases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->string('driver', 20)->default('pgsql');
            $table->string('host', 255);
            $table->unsignedInteger('port')->default(5432);
            $table->string('database_name', 120);
            $table->string('database_username', 120);
            $table->text('database_password_encrypted');
            $table->string('database_schema', 64)->nullable();

            $table->string('status', 30)->default('provisioning'); // provisioning | ready | failed | inactive
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id']);
            $table->unique(['driver', 'host', 'port', 'database_name'], 'tenant_databases_unique_db_target');
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_databases');
    }
};

