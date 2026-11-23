<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->foreignUuid('org_unit_id')
                ->nullable()
                ->after('department')
                ->constrained('org_units')
                ->nullOnDelete();

            $table->index(['tenant_id', 'org_unit_id'], 'roles_tenant_org_unit_index');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropIndex('roles_tenant_org_unit_index');
            $table->dropForeign(['org_unit_id']);
            $table->dropColumn('org_unit_id');
        });
    }
};
