<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE media_assets DROP CONSTRAINT IF EXISTS media_assets_usage_role_allowed');
        DB::statement("ALTER TABLE media_assets ADD CONSTRAINT media_assets_usage_role_allowed CHECK (usage_role IS NULL OR usage_role IN ('general', 'product_image', 'source_video', 'raw_product_video', 'campaign_source_video', 'audio_track', 'voiceover_audio', 'edited_video_result', 'fashion_try_on_result', 'video_reference', 'model_reference', 'garment_reference', 'background_reference', 'brand_reference'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE media_assets DROP CONSTRAINT IF EXISTS media_assets_usage_role_allowed');
        DB::statement("ALTER TABLE media_assets ADD CONSTRAINT media_assets_usage_role_allowed CHECK (usage_role IS NULL OR usage_role IN ('general', 'product_image', 'source_video', 'raw_product_video', 'campaign_source_video', 'audio_track', 'voiceover_audio', 'edited_video_result', 'video_reference', 'model_reference', 'garment_reference', 'background_reference', 'brand_reference'))");
    }
};
