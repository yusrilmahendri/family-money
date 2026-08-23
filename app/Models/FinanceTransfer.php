<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFinanceEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FinanceTransfer extends Model
{
    /** @use HasFactory<\Database\Factories\FinanceTransferFactory> */
    use BelongsToFinanceEntity, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'source_account_id',
        'destination_account_id',
        'amount',
        'transaction_date',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FinanceTransfer $transfer): void {
            if (blank($transfer->public_id)) {
                $transfer->public_id = static::generatePublicId();
            }

            if ((int) $transfer->source_account_id === (int) $transfer->destination_account_id) {
                throw new InvalidArgumentException('Transfer source and destination must differ.');
            }

            if ((float) $transfer->amount <= 0) {
                throw new InvalidArgumentException('Transfer amount must be greater than 0.');
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'source_account_id');
    }

    public function destinationAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class, 'destination_account_id');
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = (string) Str::ulid();
        } while (static::query()->where('public_id', $publicId)->exists());

        return $publicId;
    }
}
