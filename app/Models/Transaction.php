<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\category;
use App\Models\TransactionItem;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory;

    protected $fillable = ['category_id', 'amount', 'description', 'transaction_date', 'nota', 'keterangan_detail'];
    protected $casts = [
    'transaction_date' => 'date',
];

    public function categorys(){
        return $this->belongsTo(Category::class);
    }

    public function items(){
        return $this->hasMany(TransactionItem::class, 'transaction_id');
    }
}
