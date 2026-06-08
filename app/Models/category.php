<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['id', 'name', 'context'];
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
