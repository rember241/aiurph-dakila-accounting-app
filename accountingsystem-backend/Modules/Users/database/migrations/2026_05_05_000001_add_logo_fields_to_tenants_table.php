<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            if (!Schema::hasColumn('tenants', 'logo_path')) {
                $table->string('logo_path', 512)->nullable()->after('address');
            }

            if (!Schema::hasColumn('tenants', 'logo_mime')) {
                $table->string('logo_mime', 120)->nullable()->after('logo_path');
            }

            if (!Schema::hasColumn('tenants', 'logo_size')) {
                $table->unsignedInteger('logo_size')->nullable()->after('logo_mime');
            }

            if (!Schema::hasColumn('tenants', 'logo_width')) {
                $table->unsignedSmallInteger('logo_width')->nullable()->after('logo_size');
            }

            if (!Schema::hasColumn('tenants', 'logo_height')) {
                $table->unsignedSmallInteger('logo_height')->nullable()->after('logo_width');
            }

            if (!Schema::hasColumn('tenants', 'logo_updated_at')) {
                $table->timestamp('logo_updated_at')->nullable()->after('logo_height');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            foreach ([
                'logo_updated_at',
                'logo_height',
                'logo_width',
                'logo_size',
                'logo_mime',
                'logo_path',
            ] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
