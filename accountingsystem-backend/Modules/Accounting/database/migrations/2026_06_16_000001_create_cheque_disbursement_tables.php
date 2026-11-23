<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cheque_books', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('bank_account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('bank_name', 160);
            $table->string('bank_branch', 160)->nullable();
            $table->string('bank_account_number', 80)->nullable();
            $table->string('cheque_number_prefix', 20)->nullable();
            $table->unsignedInteger('start_number');
            $table->unsignedInteger('end_number');
            $table->unsignedInteger('next_number');
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();

            $table->index(['company_id', 'status'], 'cheque_books_company_status_idx');
            $table->unique(['company_id', 'bank_account_id', 'name'], 'cheque_books_company_bank_name_unique');
        });

        Schema::create('cheque_templates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('bank_name', 160);
            $table->decimal('width_mm', 8, 2)->default(203.20);
            $table->decimal('height_mm', 8, 2)->default(76.20);
            $table->string('status', 32)->default('active')->index();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();

            $table->index(['company_id', 'bank_name'], 'cheque_templates_company_bank_idx');
        });

        Schema::create('cheque_template_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('cheque_template_id')->constrained('cheque_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('date_format', 40)->default('MM-DD-YYYY');
            $table->json('layout');
            $table->json('default_print_options')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['cheque_template_id', 'version'], 'cheque_template_versions_template_version_unique');
            $table->index(['company_id', 'is_active'], 'cheque_template_versions_company_active_idx');
        });

        Schema::create('cheque_disbursements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('cheque_book_id')->constrained('cheque_books')->restrictOnDelete();
            $table->foreignUuid('bank_account_id')->constrained('accounts')->restrictOnDelete();
            $table->foreignUuid('cheque_template_version_id')->nullable()->constrained('cheque_template_versions')->nullOnDelete();
            $table->foreignUuid('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignUuid('reversal_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('cheque_number', 60)->nullable();
            $table->date('cheque_date');
            $table->string('payee_name', 180);
            $table->decimal('amount', 15, 2);
            $table->string('amount_in_words', 255);
            $table->string('crossing_type', 40)->default('account_payee_only');
            $table->string('bearer_action', 40)->default('strike_bearer');
            $table->string('reference_no', 100)->nullable();
            $table->text('memo')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamp('spoiled_at')->nullable();
            $table->text('spoil_reason')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status', 'cheque_date'], 'cheque_disbursements_company_status_date_idx');
            $table->unique(['company_id', 'cheque_book_id', 'cheque_number'], 'cheque_disbursements_company_book_number_unique');
        });

        Schema::create('cheque_disbursement_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('cheque_disbursement_id')->constrained('cheque_disbursements')->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->string('description', 255)->nullable();
            $table->decimal('amount', 15, 2);
            $table->timestamps();

            $table->index(['cheque_disbursement_id', 'account_id'], 'cheque_disbursement_lines_cheque_account_idx');
        });

        Schema::create('cheque_print_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('cheque_disbursement_id')->constrained('cheque_disbursements')->cascadeOnDelete();
            $table->foreignUuid('cheque_template_version_id')->nullable()->constrained('cheque_template_versions')->nullOnDelete();
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('print_type', 40);
            $table->decimal('x_offset_mm', 8, 2)->default(0);
            $table->decimal('y_offset_mm', 8, 2)->default(0);
            $table->text('reason')->nullable();
            $table->json('payload_snapshot')->nullable();
            $table->timestamp('printed_at');
            $table->timestamps();

            $table->index(['company_id', 'print_type', 'printed_at'], 'cheque_print_logs_company_type_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cheque_print_logs');
        Schema::dropIfExists('cheque_disbursement_lines');
        Schema::dropIfExists('cheque_disbursements');
        Schema::dropIfExists('cheque_template_versions');
        Schema::dropIfExists('cheque_templates');
        Schema::dropIfExists('cheque_books');
    }
};
