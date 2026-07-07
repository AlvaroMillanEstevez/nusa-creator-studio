<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table) {
            $table->string('usage_role')->nullable()->after('type');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE media_assets ADD CONSTRAINT media_assets_usage_role_allowed CHECK (usage_role IS NULL OR usage_role IN ('general', 'product_image', 'source_video', 'audio_track', 'model_reference', 'garment_reference', 'background_reference', 'brand_reference'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE media_assets DROP CONSTRAINT IF EXISTS media_assets_usage_role_allowed');
        }

        Schema::table('media_assets', function (Blueprint $table) {
            $table->dropColumn('usage_role');
        });
    }
};