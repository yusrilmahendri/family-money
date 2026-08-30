<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFinanceAccount;
use App\Models\Concerns\BelongsToFinanceEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use BelongsToFinanceAccount, BelongsToFinanceEntity, HasFactory;

    protected $fillable = [
        'finance_entity_id',
        'finance_account_id',
        'category_id',
        'context',
        'amount',
        'description',
        'detail_description',
        'transaction_date',
        'nota',
        'keterangan_detail',
        'reversed_at',
        'reversed_reason',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'reversed_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class, 'transaction_id');
    }

    /**
     * Filter berdasarkan konteks aktif (PRIBADI / USAHA_KEBUN).
     */
    public function scopeForContext($query, ?string $context)
    {
        return $query->where('context', $context);
    }

    /**
     * Rincian pengeluaran: kolom baru, fallback ke keterangan_detail legacy.
     */
    public function resolvedDetailDescription(): ?string
    {
        $detail = $this->detail_description ?? $this->keterangan_detail;
        $detail = is_string($detail) ? trim($detail) : '';

        return $detail === '' ? null : $detail;
    }

    /**
     * Label aman untuk Insight AI tanpa menggandakan transaksi.
     */
    public function insightDescriptionLabel(): string
    {
        $description = trim((string) $this->description);
        $detail = $this->resolvedDetailDescription();

        if ($detail === null) {
            return $description;
        }

        return 'Deskripsi: '.($description !== '' ? $description : '—')."\nDetail: ".$detail;
    }
}
