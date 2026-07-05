<?php

namespace App\Models;

use App\Enums\CreditTransactionType;
use Database\Factories\CreditTransactionFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'generation_id',
    'type',
    'amount',
    'balance_after',
    'reason',
    'metadata',
])]
class CreditTransaction extends Model
{
    /** @use HasFactory<CreditTransactionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (CreditTransaction $transaction): void {
            if ($transaction->amount < 0) {
                throw new DomainException('Credit transaction amount cannot be negative.');
            }

            if ($transaction->balance_after < 0) {
                throw new DomainException('Credit transaction balance cannot be negative.');
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
     * @return BelongsTo<Generation, $this>
     */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(Generation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CreditTransactionType::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
            'metadata' => 'array',
        ];
    }
}
