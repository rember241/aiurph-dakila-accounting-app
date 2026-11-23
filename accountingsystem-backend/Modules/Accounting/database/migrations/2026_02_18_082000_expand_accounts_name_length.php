<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE accounts ALTER COLUMN name TYPE VARCHAR(120)');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE accounts MODIFY name VARCHAR(120)');
            return;
        }

        if ($driver === 'sqlite') {
            // SQLite handles ALTER COLUMN TYPE differently; tests run with short names anyway.
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('name', 120)->change();
        });
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE accounts ALTER COLUMN name TYPE VARCHAR(50)');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE accounts MODIFY name VARCHAR(50)');
            return;
        }

        if ($driver === 'sqlite') {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->string('name', 50)->change();
        });
    }
};

