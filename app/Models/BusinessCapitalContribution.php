<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BusinessCapitalContribution extends Model
{
    /** @use HasFactory<\Database\Factories\BusinessCapitalContributionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'source_entity_id',
        'source_account_id',
        'business_entity_id',
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
        static::creating(function (BusinessCapitalContribution $contribution): void {
            if (blank($contribution->public_id)) {
                $contribution->public_id = static::generatePublicId();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function sourceEntity(): BelongsTo
    {
        return $this->belongsTo(FinanceEntity::class, 'source_entity_id');
    }

    public function businessEntity(): BelongsTo
    {
        return $this->belongsTo(FinanceEntity::class, 'business_entity_id');
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
