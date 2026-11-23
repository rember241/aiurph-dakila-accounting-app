<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (!Schema::hasColumn('tenants', 'document_footer_text')) {
                $table->string('document_footer_text', 500)
                    ->nullable()
                    ->after('document_header_style');
            }

            if (!Schema::hasColumn('tenants', 'document_footer_alignment')) {
                $table->string('document_footer_alignment', 16)
                    ->default('center')
                    ->after('document_footer_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            foreach ([
                'document_footer_alignment',
                'document_footer_text',
            ] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
