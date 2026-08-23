<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFinanceAccount;
use App\Models\Concerns\BelongsToFinanceEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Income extends Model
{
    use BelongsToFinanceAccount, BelongsToFinanceEntity, HasFactory;

    protected $fillable = [
        'finance_entity_id',
        'finance_account_id',
        'category_id',
        'context',
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

    /**
     * Legacy /apps saldo-list row. Entity Income no longer creates this.
     */
    public function saldo(): HasOne
    {
        return $this->hasOne(Saldo::class);
    }

    /**
     * Filter berdasarkan konteks aktif (PRIBADI / USAHA_KEBUN).
     */
    public function scopeForContext($query, ?string $context)
    {
        return $query->where('context', $context);
    }
}
