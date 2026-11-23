<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use function Laravel\Prompts\table;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the table structure
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 50)->unique();
            $table->string('name', 50);
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense'])->index();

            $table->uuid('parent_id')->nullable()->index();
            $table->boolean('is_posting')->default(true)->index();

            $table->enum('status', ['active', 'inactive'])->default('active')->index();
            $table->enum('normal_balance', ['debit', 'credit'])->index();
            $table->boolean('is_contra')->default(false)->index();

            $table->unsignedBigInteger('account_type_id')->nullable()->index();
            $table->unsignedBigInteger('detail_type_id')->nullable()->index();

            $table->string('description', 255)->nullable();
            $table->timestamps();

            // Useful for indexing(search/sort operations)
            $table->index(['type', 'status']);
            $table->index(['code', 'name']);
        });

        // Add the self-referencing foreign key
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('accounts')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
