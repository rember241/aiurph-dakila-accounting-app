<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Phase 1 foundation: assign roles in the context of a tenant membership.
     */
    public function up(): void
    {
        Schema::create('membership_roles', function (Blueprint $table) {
            $table->id();

            $table->foreignUuid('membership_id')
                ->constrained('tenant_memberships')
                ->cascadeOnDelete();

            $table->foreignUuid('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['membership_id', 'role_id']);
            $table->index(['role_id', 'membership_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_roles');
    }
};

