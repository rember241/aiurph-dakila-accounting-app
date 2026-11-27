<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Accounting\Support\AccountCategoryDetailTypes;

return new class extends Migration
{
    public function up(): void
    {
        AccountCategoryDetailTypes::install();
    }

    public function down(): void
    {
        // Keep lookup records that may already be assigned to accounts.
    }
};
