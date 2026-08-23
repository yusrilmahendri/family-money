<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProfitDistribution extends Model
{
    /** @use HasFactory<\Database\Factories\ProfitDistributionFactory> */
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
        'distribution_date',
        'period_start',
        'period_end',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'distribution_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProfitDistribution $distribution): void {
            if (blank($distribution->public_id)) {
                $distribution->public_id = static::generatePublicId();
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
