<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('credits_balance')->default(20)->after('password');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'ALTER TABLE users ADD CONSTRAINT users_credits_balance_non_negative CHECK (credits_balance >= 0)'
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                'ALTER TABLE users DROP CONSTRAINT IF EXISTS users_credits_balance_non_negative'
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('credits_balance');
        });
    }
};
