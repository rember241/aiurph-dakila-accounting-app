<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (!Schema::hasColumn('tenants', 'document_show_company_logo')) {
                $table->boolean('document_show_company_logo')->default(true)->after('invoice_primary_color');
            }

            if (!Schema::hasColumn('tenants', 'document_logo_position')) {
                $table->string('document_logo_position', 16)->default('left')->after('document_show_company_logo');
            }

            if (!Schema::hasColumn('tenants', 'document_show_company_name')) {
                $table->boolean('document_show_company_name')->default(true)->after('document_logo_position');
            }

            if (!Schema::hasColumn('tenants', 'document_show_company_address')) {
                $table->boolean('document_show_company_address')->default(true)->after('document_show_company_name');
            }

            if (!Schema::hasColumn('tenants', 'document_show_company_contact')) {
                $table->boolean('document_show_company_contact')->default(true)->after('document_show_company_address');
            }

            if (!Schema::hasColumn('tenants', 'document_show_document_title')) {
                $table->boolean('document_show_document_title')->default(true)->after('document_show_company_contact');
            }

            if (!Schema::hasColumn('tenants', 'document_accent_color')) {
                $table->string('document_accent_color', 7)->nullable()->after('document_show_document_title');
            }

            if (!Schema::hasColumn('tenants', 'document_header_alignment')) {
                $table->string('document_header_alignment', 16)->default('left')->after('document_accent_color');
            }

            if (!Schema::hasColumn('tenants', 'document_header_style')) {
                $table->string('document_header_style', 16)->default('standard')->after('document_header_alignment');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            foreach ([
                'document_header_style',
                'document_header_alignment',
                'document_accent_color',
                'document_show_document_title',
                'document_show_company_contact',
                'document_show_company_address',
                'document_show_company_name',
                'document_logo_position',
                'document_show_company_logo',
            ] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
