<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (!Schema::hasColumn('tenants', 'document_show_page_numbers')) {
                $table->boolean('document_show_page_numbers')
                    ->default(true)
                    ->after('document_footer_alignment');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (Schema::hasColumn('tenants', 'document_show_page_numbers')) {
                $table->dropColumn('document_show_page_numbers');
            }
        });
    }
};
