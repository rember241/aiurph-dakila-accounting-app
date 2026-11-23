<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('system_generated')
                ->default(false)
                ->after('tenant_id');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->index(['tenant_id', 'system_generated'], 'roles_tenant_system_generated_index');
        });

        DB::table('roles')
            ->whereIn('key', ['admin', 'accountant', 'viewer'])
            ->update(['system_generated' => true]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex('roles_tenant_system_generated_index');
            $table->dropColumn('system_generated');
        });
    }
};
