<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Category;
use App\Models\Income;

class Saldo extends Model
{
    /** @use HasFactory<\Database\Factories\SaldoFactory> */
    use HasFactory;

    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function income()
    {
        return $this->belongsTo(Income::class);
    }

    /**
     * Saldo yang otomatis dibuat dari Pemasukan Usaha.
     */
    public function scopeAutoFromIncome($query)
    {
        return $query->whereNotNull('income_id');
    }

    /**
     * Saldo yang dibuat manual (bukan dari pemasukan).
     */
    public function scopeManual($query)
    {
        return $query->whereNull('income_id');
    }
}
