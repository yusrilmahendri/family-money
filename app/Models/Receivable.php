<?php

namespace App\Models;

use App\Enums\ReceivableStatus;
use App\Models\Concerns\BelongsToFinanceEntity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Receivable extends Model
{
    /** @use HasFactory<\Database\Factories\ReceivableFactory> */
    use BelongsToFinanceEntity, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'finance_entity_id',
        'party_name',
        'description',
        'principal_amount',
        'remaining_balance',
        'receivable_date',
        'due_date',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'principal_amount' => 'decimal:2',
            'remaining_balance' => 'decimal:2',
            'receivable_date' => 'date',
            'due_date' => 'date',
            'status' => ReceivableStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Receivable $receivable): void {
            if (blank($receivable->public_id)) {
                $receivable->public_id = static::generatePublicId();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReceivablePayment::class);
    }

    public function computedStatus(): ReceivableStatus
    {
        return ReceivableStatus::fromState(
            (float) $this->principal_amount,
            (float) $this->remaining_balance,
            $this->due_date
        );
    }

    public function syncStatus(): void
    {
        $this->status = $this->computedStatus();
    }

    public function hasPayments(): bool
    {
        return $this->payments()->exists();
    }

    public function paidTotal(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = (string) Str::ulid();
        } while (static::query()->where('public_id', $publicId)->exists());

        return $publicId;
    }
}
