<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    protected $fillable = [
        'title',
        'principal_total',
        'remaining_balance',
        'monthly_installment',
        'due_day',
        'start_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'principal_total' => 'decimal:2',
            'remaining_balance' => 'decimal:2',
            'monthly_installment' => 'decimal:2',
            'start_date' => 'date',
        ];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }
}
