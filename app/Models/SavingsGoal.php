<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFinanceEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingsGoal extends Model
{
    use BelongsToFinanceEntity;

    protected $fillable = [
        'finance_entity_id',
        'title',
        'target_amount',
        'deadline',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'deadline' => 'date',
        ];
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(GoalContribution::class);
    }

    public function savedTotal(): float
    {
        return (float) $this->contributions()->sum('amount');
    }
}
