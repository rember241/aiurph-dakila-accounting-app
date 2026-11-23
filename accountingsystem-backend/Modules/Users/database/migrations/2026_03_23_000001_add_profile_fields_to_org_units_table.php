<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('org_units', function (Blueprint $table): void {
            $table->string('code', 24)->nullable()->after('name');
            $table->string('location', 120)->nullable()->after('code');
            $table->string('description', 240)->nullable()->after('location');

            $table->index(['company_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('org_units', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'code']);
            $table->dropColumn(['code', 'location', 'description']);
        });
    }
};
