<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFinanceEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use BelongsToFinanceEntity, HasFactory;

    protected $fillable = ['id', 'name', 'context', 'finance_entity_id'];
    protected $table = 'categories';

    public function saldos()
    {
        return $this->hasMany(Saldo::class);
    }

    /**
     * Filter berdasarkan konteks aktif (PRIBADI / USAHA_KEBUN).
     */
    public function scopeForContext($query, ?string $context)
    {
        return $query->where('context', $context);
    }
}
