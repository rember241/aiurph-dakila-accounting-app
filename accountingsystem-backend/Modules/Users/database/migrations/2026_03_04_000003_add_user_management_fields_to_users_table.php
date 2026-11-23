<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('remember_token'); // active | inactive
            $table->string('department', 120)->nullable()->after('status');
            $table->string('access_level', 20)->default('read')->after('department'); // read | write
            $table->string('avatar_url', 500)->nullable()->after('access_level');
            $table->timestamp('last_active_at')->nullable()->after('avatar_url');

            $table->index(['status']);
            $table->index(['department']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['department']);
            $table->dropColumn(['status', 'department', 'access_level', 'avatar_url', 'last_active_at']);
        });
    }
};

