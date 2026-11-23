<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUuid('company_id')
                ->nullable()
                ->after('last_active_at')
                ->constrained('tenants')
                ->nullOnDelete();

            $table->foreignUuid('primary_org_unit_id')
                ->nullable()
                ->after('company_id')
                ->constrained('org_units')
                ->nullOnDelete();

            $table->index(['company_id'], 'users_company_id_index');
            $table->index(['primary_org_unit_id'], 'users_primary_org_unit_id_index');
        });

        $userIds = DB::table('users')->pluck('id');

        foreach ($userIds as $userId) {
            $companyId = DB::table('tenants')
                ->where('owner_user_id', $userId)
                ->orderBy('created_at')
                ->value('id');

            if ($companyId === null) {
                $companyId = DB::table('tenant_memberships')
                    ->where('user_id', $userId)
                    ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                    ->orderBy('created_at')
                    ->value('tenant_id');
            }

            if ($companyId !== null) {
                DB::table('users')
                    ->where('id', $userId)
                    ->update([
                        'company_id' => (string) $companyId,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_company_id_index');
            $table->dropIndex('users_primary_org_unit_id_index');
            $table->dropForeign(['company_id']);
            $table->dropForeign(['primary_org_unit_id']);
            $table->dropColumn(['company_id', 'primary_org_unit_id']);
        });
    }
};
