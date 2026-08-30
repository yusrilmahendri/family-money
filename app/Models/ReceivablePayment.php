<?php

namespace App\Models;

use App\Enums\ReceivablePaymentSourceType;
use App\Enums\ReceivablePaymentStatus;
use App\Models\Concerns\BelongsToFinanceAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReceivablePayment extends Model
{
    /** @use HasFactory<\Database\Factories\ReceivablePaymentFactory> */
    use BelongsToFinanceAccount, HasFactory;

    protected $attributes = [
        'status' => 'ACTIVE',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'receivable_id',
        'finance_account_id',
        'amount',
        'payment_date',
        'description',
        'source_type',
        'source_public_id',
        'status',
        'reversed_at',
        'reversed_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'source_type' => ReceivablePaymentSourceType::class,
            'status' => ReceivablePaymentStatus::class,
            'reversed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ReceivablePayment $payment): void {
            if (blank($payment->public_id)) {
                $payment->public_id = static::generatePublicId();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = (string) Str::ulid();
        } while (static::query()->where('public_id', $publicId)->exists());

        return $publicId;
    }
}
