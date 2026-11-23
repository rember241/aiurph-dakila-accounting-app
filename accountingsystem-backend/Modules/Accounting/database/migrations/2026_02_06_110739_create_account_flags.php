<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('account_flags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('account_id')->index();

            $table->string('flag', 80);
            $table->string('value', 120);
            $table->string('scope', 40)->default('account');

            $table->timestamps();

            $table->unique(['account_id', 'flag', 'scope']);
            $table->index(['flag', 'value']);

            $table->foreign('account_id')
                ->references('id')
                ->on('accounts')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_flags');
    }
};
