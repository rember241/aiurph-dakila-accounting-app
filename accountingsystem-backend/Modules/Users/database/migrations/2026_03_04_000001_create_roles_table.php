<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->string('key', 50)->unique();   // machine key (e.g. admin, accountant)
            $table->string('name', 120);           // display name
            $table->string('department', 120)->nullable();
            $table->string('status', 20)->default('active');        // active | inactive
            $table->string('access_level', 20)->default('read');    // read | write (high-level hint for UI)
            $table->string('permission_scope', 30)->default('global'); // global | department | own

            // JSON map: { "module": ["read","write", ...], ... }
            $table->json('module_permissions')->nullable();
            $table->text('description')->nullable();

            $table->timestamps();

            $table->index(['status', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};

