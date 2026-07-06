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

    public const IMPLEMENTED_ROUTES = [
        'affiliate_campaign' => '/campaigns/new',
        'video_enhancer' => '/video-enhancer/new',
        'product_to_video' => '/product-to-video/new',
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
