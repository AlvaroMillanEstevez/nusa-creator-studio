<?php

namespace App\Models;

use Database\Factories\MediaAssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'project_id',
    'type',
    'usage_role',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size_bytes',
    'metadata',
])]
class MediaAsset extends Model
{
    public const TYPES = [
        'image',
        'video',
        'audio',
        'document',
        'other',
    ];

    public const USAGE_ROLES = [
        'general',
        'product_image',
        'source_video',
        'audio_track',
        'model_reference',
        'garment_reference',
        'background_reference',
        'brand_reference',
    ];

    /** @use HasFactory<MediaAssetFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'metadata' => 'array',
        ];
    }
}
