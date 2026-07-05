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

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->credits_balance !== null && $user->credits_balance < 0) {
                throw new DomainException('Platform credit balance cannot be negative.');
            }
        });
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
