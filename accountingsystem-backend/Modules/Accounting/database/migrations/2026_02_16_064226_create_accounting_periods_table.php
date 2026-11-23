<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Enums\PeriodManagement\FiscalYearStatus;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fiscal_year_id')
                ->constrained('fiscal_years')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('period_number');
            $table->string('name', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 50)
                ->default(FiscalYearStatus::OPEN->value)
                ->index();
            $table->boolean('lock_ap')->default(false);
            $table->boolean('lock_ar')->default(false);
            $table->boolean('lock_inventory')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['fiscal_year_id', 'period_number'], 'accounting_periods_fy_period_unique');
            $table->index(['fiscal_year_id', 'status'], 'accounting_periods_fy_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
    }
};

