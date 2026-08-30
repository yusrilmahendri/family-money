<?php

namespace App\Models;

use App\Enums\PlantationOperatingBudgetStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PlantationOperatingBudget extends Model
{
    /**
     * public_id is generated internally and must never come from user input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'finance_entity_id',
        'name',
        'period_start',
        'period_end',
        'allocated_amount',
        'status',
        'last_synced_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'allocated_amount' => 'decimal:2',
            'status' => PlantationOperatingBudgetStatus::class,
            'last_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PlantationOperatingBudget $budget): void {
            if (blank($budget->public_id)) {
                $budget->public_id = static::generatePublicId();
            }

            if (! $budget->status instanceof PlantationOperatingBudgetStatus) {
                $budget->status = PlantationOperatingBudgetStatus::DRAFT;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function financeEntity(): BelongsTo
    {
        return $this->belongsTo(FinanceEntity::class);
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = (string) Str::ulid();
        } while (static::query()->where('public_id', $publicId)->exists());

        return $publicId;
    }
}
