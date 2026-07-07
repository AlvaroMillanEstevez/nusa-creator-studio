<?php

namespace Database\Factories;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    public function definition(): array
    {
        $filename = Str::uuid().'.jpg';

        return [
            'user_id' => User::factory(),
            'project_id' => null,
            'type' => 'image',
            'usage_role' => null,
            'disk' => 's3',
            'path' => 'media/'.$filename,
            'original_name' => $filename,
            'mime_type' => 'image/jpeg',
            'size_bytes' => fake()->numberBetween(1_000, 10_000_000),
            'metadata' => null,
        ];
    }
}
