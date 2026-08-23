<?php

namespace App\Models;

use App\Enums\FinanceAccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FinanceAccount extends Model
{
    /** @use HasFactory<\Database\Factories\FinanceAccountFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'bank_name',
        'account_number',
        'description',
        'opening_balance',
        'is_active',
        'is_default',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'opening_balance' => 0,
        'is_active' => true,
        'is_default' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FinanceAccountType::class,
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FinanceAccount $account): void {
            if (blank($account->name)) {
                throw new InvalidArgumentException('FinanceAccount name is required.');
            }

            if (! $account->type instanceof FinanceAccountType) {
                throw new InvalidArgumentException('FinanceAccount type is invalid.');
            }

            if (blank($account->public_id)) {
                $account->public_id = static::generatePublicId();
            }

            if ($account->is_active === null) {
                $account->is_active = true;
            }

            if ($account->is_default === null) {
                $account->is_default = false;
            }

            if ($account->opening_balance === null) {
                $account->opening_balance = 0;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function financeEntity(): BelongsTo
    {
        return $this->belongsTo(FinanceEntity::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    public function debtPayments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    public function receivablePayments(): HasMany
    {
        return $this->hasMany(ReceivablePayment::class);
    }

    public function goalContributions(): HasMany
    {
        return $this->hasMany(GoalContribution::class);
    }

    public function budgetActivities(): HasMany
    {
        return $this->hasMany(BudgetActivity::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(FinanceTransfer::class, 'source_account_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(FinanceTransfer::class, 'destination_account_id');
    }

    public function outgoingCapitalContributions(): HasMany
    {
        return $this->hasMany(BusinessCapitalContribution::class, 'source_account_id');
    }

    public function incomingCapitalContributions(): HasMany
    {
        return $this->hasMany(BusinessCapitalContribution::class, 'destination_account_id');
    }

    public function outgoingOwnerWithdrawals(): HasMany
    {
        return $this->hasMany(OwnerWithdrawal::class, 'source_account_id');
    }

    public function incomingOwnerWithdrawals(): HasMany
    {
        return $this->hasMany(OwnerWithdrawal::class, 'destination_account_id');
    }

    public function outgoingProfitDistributions(): HasMany
    {
        return $this->hasMany(ProfitDistribution::class, 'source_account_id');
    }

    public function incomingProfitDistributions(): HasMany
    {
        return $this->hasMany(ProfitDistribution::class, 'destination_account_id');
    }

    public function maskedAccountNumber(): string
    {
        $number = preg_replace('/\s+/', '', (string) $this->account_number);

        if ($number === '' || $number === null) {
            return '—';
        }

        $length = strlen($number);
        $visible = $length <= 4 ? 1 : 4;

        return str_repeat('*', max($length - $visible, 1)).substr($number, -$visible);
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = (string) Str::ulid();
        } while (static::query()->where('public_id', $publicId)->exists());

        return $publicId;
    }
}
