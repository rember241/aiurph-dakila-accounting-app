<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->uuid('parent_org_unit_id')->nullable();
            $table->string('type', 40);
            $table->string('name', 160);
            $table->string('status', 20)->default('active');
            $table->boolean('is_root')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'parent_org_unit_id']);
            $table->index(['company_id', 'is_root']);
        });

        Schema::table('org_units', function (Blueprint $table): void {
            $table->foreign('parent_org_unit_id')
                ->references('id')
                ->on('org_units')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_units');
    }
};
