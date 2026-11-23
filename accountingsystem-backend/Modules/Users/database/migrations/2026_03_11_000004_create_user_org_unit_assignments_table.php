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
        Schema::create('user_org_unit_assignments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignUuid('company_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->foreignUuid('org_unit_id')
                ->constrained('org_units')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'org_unit_id'], 'user_org_unit_assignments_user_org_unit_unique');
            $table->index(['company_id', 'user_id'], 'user_org_unit_assignments_company_user_index');
            $table->index(['company_id', 'org_unit_id'], 'user_org_unit_assignments_company_org_unit_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_org_unit_assignments');
    }
};
