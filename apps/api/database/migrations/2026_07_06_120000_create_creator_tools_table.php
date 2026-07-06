<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creator_tools', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->text('short_description');
            $table->text('long_description')->nullable();
            $table->string('category');
            $table->string('status');
            $table->string('route')->nullable();
            $table->unsignedInteger('credit_cost')->default(1);
            $table->string('credit_cost_label')->nullable();
            $table->string('badge_label')->nullable();
            $table->boolean('is_recommended')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_visible', 'status', 'sort_order']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                "ALTER TABLE creator_tools ADD CONSTRAINT creator_tools_status_valid CHECK (status IN ('available', 'coming_soon', 'maintenance', 'disabled'))"
            );
            Schema::getConnection()->statement(
                'ALTER TABLE creator_tools ADD CONSTRAINT creator_tools_credit_cost_non_negative CHECK (credit_cost >= 0)'
            );
            Schema::getConnection()->statement(
                'ALTER TABLE creator_tools ADD CONSTRAINT creator_tools_sort_order_non_negative CHECK (sort_order >= 0)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_tools');
    }
};
