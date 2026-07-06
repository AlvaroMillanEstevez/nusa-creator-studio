<?php

namespace App\Models;

use Database\Factories\GenerationFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
    'input_payload',
    'output_payload',
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

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_QUEUED,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        'pending', // Legacy compatibility for records created before async jobs.
    ];

    protected static function booted(): void
    {
        static::saving(function (Generation $generation): void {
            if ($generation->credits_charged < 0) {
                throw new DomainException('Generation credits charged cannot be negative.');
            }

            if (! in_array($generation->status, self::STATUSES, true)) {
                throw new DomainException('Invalid generation status.');
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

    /** @return Attribute<array<string, mixed>|null, array<string, mixed>|null> */
    protected function requestPayload(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->input_payload,
            set: fn (?array $value) => ['input_payload' => $value === null
                ? null
                : json_encode($value, JSON_THROW_ON_ERROR)],
        );
    }

    /** @return Attribute<array<string, mixed>|null, array<string, mixed>|null> */
    protected function resultPayload(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->output_payload,
            set: fn (?array $value) => ['output_payload' => $value === null
                ? null
                : json_encode($value, JSON_THROW_ON_ERROR)],
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost_usd' => 'decimal:4',
            'credits_charged' => 'integer',
            'input_payload' => 'array',
            'output_payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
