<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFinanceAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ReceivablePayment extends Model
{
    /** @use HasFactory<\Database\Factories\ReceivablePaymentFactory> */
    use BelongsToFinanceAccount, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'receivable_id',
        'finance_account_id',
        'amount',
        'payment_date',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
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
