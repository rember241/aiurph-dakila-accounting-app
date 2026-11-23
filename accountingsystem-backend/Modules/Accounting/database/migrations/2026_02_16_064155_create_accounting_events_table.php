<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('event_type', 100)->index();
            $table->string('source_module', 50)->index();
            $table->string('source_type', 100)->index();
            $table->uuid('source_id')->index();
            $table->date('event_date')->index();
            $table->json('payload');
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending')->index();
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(
                ['source_module', 'source_type', 'source_id'],
                'accounting_events_source_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_events');
    }
};

