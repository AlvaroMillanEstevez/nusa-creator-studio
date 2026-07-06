<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'credits_balance',
    'role',
    'plan_key',
    'is_unlimited',
    'monthly_credit_allowance',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'credits_balance' => 20,
        'role' => 'free_user',
        'plan_key' => 'free',
        'is_unlimited' => false,
        'monthly_credit_allowance' => 20,
    ];

    public const ROLES = [
        'admin',
        'internal_creator',
        'subscriber',
        'free_user',
    ];

    public const PLAN_KEYS = [
        'free',
        'starter',
        'creator',
        'pro',
        'internal_unlimited',
    ];

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->credits_balance !== null && $user->credits_balance < 0) {
                throw new DomainException('Platform credit balance cannot be negative.');
            }

            if (! in_array($user->role, self::ROLES, true)) {
                throw new DomainException('Invalid user role.');
            }

            if (! in_array($user->plan_key, self::PLAN_KEYS, true)) {
                throw new DomainException('Invalid user plan.');
            }

            if ($user->monthly_credit_allowance !== null && $user->monthly_credit_allowance < 0) {
                throw new DomainException('Monthly credit allowance cannot be negative.');
            }
        });
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isInternalCreator(): bool
    {
        return $this->role === 'internal_creator';
    }

    public function isSubscriber(): bool
    {
        return $this->role === 'subscriber';
    }

    public function hasUnlimitedAccess(): bool
    {
        return $this->is_unlimited;
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return HasMany<MediaAsset, $this>
     */
    public function mediaAssets(): HasMany
    {
        return $this->hasMany(MediaAsset::class);
    }

    /**
     * @return HasMany<Generation, $this>
     */
    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }

    /**
     * @return HasMany<CreditTransaction, $this>
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credits_balance' => 'integer',
            'is_unlimited' => 'boolean',
            'monthly_credit_allowance' => 'integer',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
