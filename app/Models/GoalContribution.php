<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFinanceAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalContribution extends Model
{
    use BelongsToFinanceAccount;

    protected $fillable = [
        'savings_goal_id',
        'finance_account_id',
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
