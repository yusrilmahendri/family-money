<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFinanceEntity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    use BelongsToFinanceEntity;

    protected $fillable = [
        'finance_entity_id',
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

    public function remainingAmount(): float
    {
        return max(0.0, (float) $this->remaining_balance);
    }

    public function isPaidOff(): bool
    {
        return $this->remainingAmount() <= 0.0;
    }

    public function totalPaid(): float
    {
        return max(0.0, (float) $this->principal_total - $this->remainingAmount());
    }

    public function paymentProgressPercentage(): float
    {
        $principal = (float) $this->principal_total;

        if ($principal <= 0.0) {
            return $this->isPaidOff() ? 100.0 : 0.0;
        }

        return min(100.0, max(0.0, round(($this->totalPaid() / $principal) * 100, 1)));
    }
}
