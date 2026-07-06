<?php

namespace App\Models;

use Database\Factories\SubscriptionPlanFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'key',
    'name',
    'short_description',
    'description',
    'price_amount',
    'currency',
    'billing_interval',
    'monthly_credits',
    'max_projects_per_month',
    'max_generations_per_month',
    'is_public',
    'is_featured',
    'is_active',
    'is_internal',
    'sort_order',
    'cta_label',
    'features',
    'limits',
    'metadata',
])]
class SubscriptionPlan extends Model
{
    /** @use HasFactory<SubscriptionPlanFactory> */
    use HasFactory;

    public const KEYS = [
        'free',
        'starter',
        'creator',
        'pro',
        'internal_unlimited',
    ];

    public const BILLING_INTERVALS = [
        'month',
        'year',
        'one_time',
        'internal',
    ];

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'plan_key', 'key');
    }

    protected static function booted(): void
    {
        static::saving(function (SubscriptionPlan $plan): void {
            if (! in_array($plan->key, self::KEYS, true)) {
                throw new DomainException('Invalid subscription plan key.');
            }

            if (! in_array($plan->billing_interval, self::BILLING_INTERVALS, true)) {
                throw new DomainException('Invalid subscription plan billing interval.');
            }

            foreach ([
                $plan->price_amount,
                $plan->monthly_credits,
                $plan->max_projects_per_month,
                $plan->max_generations_per_month,
                $plan->sort_order,
            ] as $value) {
                if ($value !== null && $value < 0) {
                    throw new DomainException('Subscription plan values cannot be negative.');
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_amount' => 'decimal:2',
            'monthly_credits' => 'integer',
            'max_projects_per_month' => 'integer',
            'max_generations_per_month' => 'integer',
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'is_internal' => 'boolean',
            'sort_order' => 'integer',
            'features' => 'array',
            'limits' => 'array',
            'metadata' => 'array',
        ];
    }
}
