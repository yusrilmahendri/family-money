<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    /** @use HasFactory<\Database\Factories\BudgetFactory> */
    use HasFactory;

    protected $fillable = [
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
}
