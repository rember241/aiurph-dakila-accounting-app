<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasColumn('tenants', 'default_cog_account_id')
            && !Schema::hasColumn('tenants', 'default_cogs_account_id')
        ) {
            DB::statement('ALTER TABLE tenants RENAME COLUMN default_cog_account_id TO default_cogs_account_id');
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn('tenants', 'default_cogs_account_id')
            && !Schema::hasColumn('tenants', 'default_cog_account_id')
        ) {
            DB::statement('ALTER TABLE tenants RENAME COLUMN default_cogs_account_id TO default_cog_account_id');
        }
    }
};
