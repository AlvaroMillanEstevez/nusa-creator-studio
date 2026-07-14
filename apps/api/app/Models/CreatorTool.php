<?php

namespace App\Models;

use Database\Factories\CreatorToolFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key',
    'title',
    'short_description',
    'long_description',
    'category',
    'service_tier',
    'output_type',
    'implementation_stage',
    'status',
    'route',
    'credit_cost',
    'credit_cost_label',
    'badge_label',
    'is_recommended',
    'is_visible',
    'sort_order',
    'metadata',
])]
class CreatorTool extends Model
{
    /** @use HasFactory<CreatorToolFactory> */
    use HasFactory;

    public const STATUSES = [
        'available',
        'coming_soon',
        'maintenance',
        'disabled',
    ];

    public const SERVICE_TIERS = [
        'basic',
        'advanced',
        'premium',
    ];

    public const OUTPUT_TYPES = [
        'text_insight',
        'script_pack',
        'assisted_media',
        'audio',
        'image',
        'video',
        'avatar',
        'mixed_media',
        'ad_plan',
    ];

    public const IMPLEMENTATION_STAGES = [
        'mock',
        'beta',
        'real_provider',
        'future',
    ];

    public const IMPLEMENTED_ROUTES = [
        'affiliate_campaign' => '/campaigns/new',
        'video_enhancer' => '/video-enhancer/new',
        'video_to_campaign' => '/dashboard?tool=video_to_campaign',
        'product_to_video' => '/product-to-video/new',
        'ai_voiceover' => '/voiceover/new',
        'fashion_try_on' => '/fashion-try-on/new',
        'motion_ai_video' => '/motion-video/new',
        'auto_video_editor' => '/dashboard?tool=auto_video_editor',
        'auto_video_ads' => '/dashboard?tool=auto_video_ads',
    ];

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    protected static function booted(): void
    {
        static::saving(function (CreatorTool $tool): void {
            if (! in_array($tool->status, self::STATUSES, true)) {
                throw new DomainException('Invalid creator tool status.');
            }

            if (! in_array($tool->service_tier, self::SERVICE_TIERS, true)) {
                throw new DomainException('Invalid creator tool service tier.');
            }

            if ($tool->output_type !== null && ! in_array($tool->output_type, self::OUTPUT_TYPES, true)) {
                throw new DomainException('Invalid creator tool output type.');
            }

            if (! in_array($tool->implementation_stage, self::IMPLEMENTATION_STAGES, true)) {
                throw new DomainException('Invalid creator tool implementation stage.');
            }

            if ($tool->credit_cost < 0 || $tool->sort_order < 0) {
                throw new DomainException('Creator tool numeric values cannot be negative.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credit_cost' => 'integer',
            'is_recommended' => 'boolean',
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }
}
