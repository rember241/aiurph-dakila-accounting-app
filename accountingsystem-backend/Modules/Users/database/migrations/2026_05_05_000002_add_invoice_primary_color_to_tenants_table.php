<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (!Schema::hasColumn('tenants', 'invoice_primary_color')) {
                $table->string('invoice_primary_color', 7)->nullable()->after('invoice_payment_note');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (Schema::hasColumn('tenants', 'invoice_primary_color')) {
                $table->dropColumn('invoice_primary_color');
            }
        });
    }
};
