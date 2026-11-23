<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone', 40)->nullable()->after('email');
            });
        }

        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'display_name')) {
                $table->string('display_name', 160)->nullable()->after('name');
            }

            if (!Schema::hasColumn('tenants', 'email')) {
                $table->string('email', 120)->nullable()->after('timezone');
            }

            if (!Schema::hasColumn('tenants', 'phone')) {
                $table->string('phone', 40)->nullable()->after('email');
            }

            if (!Schema::hasColumn('tenants', 'address')) {
                $table->string('address', 500)->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $columns = [];

            foreach (['display_name', 'email', 'phone', 'address'] as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        if (Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
    }
};
