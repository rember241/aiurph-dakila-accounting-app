<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


/**
 * Add lookup indexes to account_types and detail_types tables.
 *
 * This migration adds indexes to the account_types and detail_types tables to
 * speed up lookups based on the is_active and core_type (for account_types) or
 * account_type_id (for detail_types) columns.
 *
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('account_types')) {
            Schema::table('account_types', function (Blueprint $table) {
                if (Schema::hasColumn('account_types', 'core_type')) {
                    $table->index(['is_active', 'core_type']);
                } else {
                    $table->index(['is_active']);
                }
            });
        }

        if (Schema::hasTable('detail_types')) {
            Schema::table('detail_types', function (Blueprint $table) {
                $table->index(['is_active', 'account_type_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('account_types', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'core_type']);
            $table->dropIndex(['is_active']);
        });

        Schema::table('detail_types', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'account_type_id']);
        });
    }
};
