<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('free_user');
            $table->string('plan_key')->default('free');
            $table->boolean('is_unlimited')->default(false);
            $table->unsignedInteger('monthly_credit_allowance')->default(20);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                "ALTER TABLE users ADD CONSTRAINT users_role_valid CHECK (role IN ('admin', 'internal_creator', 'subscriber', 'free_user'))"
            );
            Schema::getConnection()->statement(
                "ALTER TABLE users ADD CONSTRAINT users_plan_key_valid CHECK (plan_key IN ('free', 'starter', 'creator', 'pro', 'internal_unlimited'))"
            );
            Schema::getConnection()->statement(
                'ALTER TABLE users ADD CONSTRAINT users_monthly_credit_allowance_non_negative CHECK (monthly_credit_allowance >= 0)'
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_valid');
            Schema::getConnection()->statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_plan_key_valid');
            Schema::getConnection()->statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_monthly_credit_allowance_non_negative');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'plan_key',
                'is_unlimited',
                'monthly_credit_allowance',
            ]);
        });
    }
};
