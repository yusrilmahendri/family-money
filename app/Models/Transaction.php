<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Category;
use App\Models\TransactionItem;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory;

    protected $fillable = ['category_id', 'context', 'amount', 'description', 'transaction_date', 'nota', 'keterangan_detail'];
    protected $casts = [
    'transaction_date' => 'date',
];

    public function category(){
        return $this->belongsTo(Category::class);
    }

    public function items(){
        return $this->hasMany(TransactionItem::class, 'transaction_id');
    }

    /**
     * Filter berdasarkan konteks aktif (PRIBADI / USAHA_KEBUN).
     */
    public function scopeForContext($query, ?string $context)
    {
        return $query->where('context', $context);
    }
}
