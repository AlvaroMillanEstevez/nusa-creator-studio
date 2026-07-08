<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creator_tools', function (Blueprint $table): void {
            $table->string('service_tier')->default('basic')->after('category');
            $table->string('output_type')->nullable()->after('service_tier');
            $table->string('implementation_stage')->default('mock')->after('output_type');

            $table->index(['service_tier', 'sort_order']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                "ALTER TABLE creator_tools ADD CONSTRAINT creator_tools_service_tier_valid CHECK (service_tier IN ('basic', 'advanced', 'premium'))"
            );
            Schema::getConnection()->statement(
                "ALTER TABLE creator_tools ADD CONSTRAINT creator_tools_output_type_valid CHECK (output_type IS NULL OR output_type IN ('text_insight', 'script_pack', 'assisted_media', 'audio', 'image', 'video', 'avatar', 'mixed_media'))"
            );
            Schema::getConnection()->statement(
                "ALTER TABLE creator_tools ADD CONSTRAINT creator_tools_implementation_stage_valid CHECK (implementation_stage IN ('mock', 'beta', 'real_provider', 'future'))"
            );
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE creator_tools DROP CONSTRAINT IF EXISTS creator_tools_implementation_stage_valid');
            Schema::getConnection()->statement('ALTER TABLE creator_tools DROP CONSTRAINT IF EXISTS creator_tools_output_type_valid');
            Schema::getConnection()->statement('ALTER TABLE creator_tools DROP CONSTRAINT IF EXISTS creator_tools_service_tier_valid');
        }

        Schema::table('creator_tools', function (Blueprint $table): void {
            $table->dropIndex(['service_tier', 'sort_order']);
            $table->dropColumn(['service_tier', 'output_type', 'implementation_stage']);
        });
    }
};
