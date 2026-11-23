<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'avatar_path')) {
                $table->string('avatar_path', 512)->nullable()->after('avatar_url');
            }

            if (!Schema::hasColumn('users', 'avatar_mime')) {
                $table->string('avatar_mime', 120)->nullable()->after('avatar_path');
            }

            if (!Schema::hasColumn('users', 'avatar_size')) {
                $table->unsignedInteger('avatar_size')->nullable()->after('avatar_mime');
            }

            if (!Schema::hasColumn('users', 'avatar_width')) {
                $table->unsignedSmallInteger('avatar_width')->nullable()->after('avatar_size');
            }

            if (!Schema::hasColumn('users', 'avatar_height')) {
                $table->unsignedSmallInteger('avatar_height')->nullable()->after('avatar_width');
            }

            if (!Schema::hasColumn('users', 'avatar_updated_at')) {
                $table->timestamp('avatar_updated_at')->nullable()->after('avatar_height');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'avatar_updated_at',
                'avatar_height',
                'avatar_width',
                'avatar_size',
                'avatar_mime',
                'avatar_path',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
