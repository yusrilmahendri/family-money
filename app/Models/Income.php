<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Income extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'source',
        'amount',
        'income_date',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'income_date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function saldo(): HasOne
    {
        return $this->hasOne(Saldo::class);
    }
}
