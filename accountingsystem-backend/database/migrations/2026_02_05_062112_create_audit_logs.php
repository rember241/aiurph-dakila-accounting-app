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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 120);
            $table->string('auditable_type');
            $table->uuid('auditable_id');

            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('meta')->nullable(); // request_id, ip, user_agent

            $table->timestamps();
            // Create indexes for the auditable_type and auditable_id columns.
            // This will speed up queries that filter by these columns.
            $table->index(['auditable_type', 'auditable_id']);

            // Create an index for the action and created_at columns.
            // This will speed up queries that filter by these columns.
            $table->index(['action', 'created_at']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
