<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFinanceEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    /** @use HasFactory<\Database\Factories\BudgetFactory> */
    use BelongsToFinanceEntity, HasFactory;

    protected $fillable = [
        'finance_entity_id',
        'category_id',
        'amount',
        'amount_saldo',
        'periode',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'periode' => 'date',
            'amount' => 'decimal:2',
            'amount_saldo' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(BudgetActivity::class)->orderBy('activity_date', 'desc');
    }

    public function plannedAmount(): float
    {
        return (float) $this->amount;
    }

    public function realizedAmount(): float
    {
        if (array_key_exists('activities_sum_amount', $this->attributes)) {
            return (float) ($this->attributes['activities_sum_amount'] ?? 0);
        }

        if ($this->relationLoaded('activities')) {
            return (float) $this->activities->sum('amount');
        }

        return (float) $this->activities()->sum('amount');
    }

    public function remainingAmount(): float
    {
        return $this->plannedAmount() - $this->realizedAmount();
    }

    public function varianceAmount(): float
    {
        return $this->remainingAmount();
    }
}
