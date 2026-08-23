<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFinanceAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtPayment extends Model
{
    use BelongsToFinanceAccount;

    protected $fillable = [
        'debt_id',
        'finance_account_id',
        'amount',
        'paid_on',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }
}
