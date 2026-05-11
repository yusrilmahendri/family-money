<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalContribution extends Model
{
    protected $fillable = [
        'savings_goal_id',
        'amount',
        'contributed_on',
    ];

    protected function casts(): array
    {
        return [
            'contributed_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function savingsGoal(): BelongsTo
    {
        return $this->belongsTo(SavingsGoal::class);
    }
}
