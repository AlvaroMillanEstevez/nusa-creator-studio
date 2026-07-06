<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('IDR');
            $table->string('billing_interval')->default('month');
            $table->unsignedInteger('monthly_credits')->default(0);
            $table->unsignedInteger('max_projects_per_month')->nullable();
            $table->unsignedInteger('max_generations_per_month')->nullable();
            $table->boolean('is_public')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_internal')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('cta_label')->nullable();
            $table->json('features')->nullable();
            $table->json('limits')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_public', 'is_active', 'sort_order']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                "ALTER TABLE subscription_plans ADD CONSTRAINT subscription_plans_key_valid CHECK (key IN ('free', 'starter', 'creator', 'pro', 'internal_unlimited'))"
            );
            Schema::getConnection()->statement(
                "ALTER TABLE subscription_plans ADD CONSTRAINT subscription_plans_interval_valid CHECK (billing_interval IN ('month', 'year', 'one_time', 'internal'))"
            );
            Schema::getConnection()->statement(
                'ALTER TABLE subscription_plans ADD CONSTRAINT subscription_plans_price_non_negative CHECK (price_amount IS NULL OR price_amount >= 0)'
            );
            Schema::getConnection()->statement(
                'ALTER TABLE subscription_plans ADD CONSTRAINT subscription_plans_monthly_credits_non_negative CHECK (monthly_credits >= 0)'
            );
            Schema::getConnection()->statement(
                'ALTER TABLE subscription_plans ADD CONSTRAINT subscription_plans_project_limit_non_negative CHECK (max_projects_per_month IS NULL OR max_projects_per_month >= 0)'
            );
            Schema::getConnection()->statement(
                'ALTER TABLE subscription_plans ADD CONSTRAINT subscription_plans_generation_limit_non_negative CHECK (max_generations_per_month IS NULL OR max_generations_per_month >= 0)'
            );
            Schema::getConnection()->statement(
                'ALTER TABLE subscription_plans ADD CONSTRAINT subscription_plans_sort_order_non_negative CHECK (sort_order >= 0)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
