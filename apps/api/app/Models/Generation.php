<?php

namespace App\Models;

use Database\Factories\GenerationFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'project_id',
    'job_type',
    'provider',
    'status',
    'cost_usd',
    'credits_charged',
    'request_payload',
    'result_payload',
    'error_message',
    'started_at',
    'finished_at',
])]
class Generation extends Model
{
    /** @use HasFactory<GenerationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Generation $generation): void {
            if ($generation->credits_charged < 0) {
                throw new DomainException('Generation credits charged cannot be negative.');
            }
        });
    }

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
     * @return HasMany<CreditTransaction, $this>
     */
    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_usd' => 'decimal:4',
            'credits_charged' => 'integer',
            'request_payload' => 'array',
            'result_payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
