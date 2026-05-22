<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'budget_id',
        'name',
        'amount',
        'activity_date',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }
}
