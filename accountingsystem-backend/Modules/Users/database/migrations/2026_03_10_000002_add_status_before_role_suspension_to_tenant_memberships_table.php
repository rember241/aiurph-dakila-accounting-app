<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table) {
            $table->string('status_before_role_suspension', 20)
                ->nullable()
                ->after('status');

            $table->index(
                ['tenant_id', 'status_before_role_suspension'],
                'tenant_memberships_role_suspension_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('tenant_memberships', function (Blueprint $table) {
            $table->dropIndex('tenant_memberships_role_suspension_status_index');
            $table->dropColumn('status_before_role_suspension');
        });
    }
};
