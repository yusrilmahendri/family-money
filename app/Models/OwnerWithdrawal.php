<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OwnerWithdrawal extends Model
{
    /** @use HasFactory<\Database\Factories\OwnerWithdrawalFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_entity_id',
        'source_account_id',
        'family_entity_id',
        'destination_account_id',
        'amount',
        'transaction_date',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OwnerWithdrawal $withdrawal): void {
            if (blank($withdrawal->public_id)) {
                $withdrawal->public_id = static::generatePublicId();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(FinanceEntity::class, 'business_entity_id');
    }

    public function familyEntity(): BelongsTo
    {
        return $this->belongsTo(FinanceEntity::class, 'family_entity_id');
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'source_account_id');
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'destination_account_id');
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = (string) Str::ulid();
        } while (static::query()->where('public_id', $publicId)->exists());

        return $publicId;
    }
}
