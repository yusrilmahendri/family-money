<?php

namespace App\Models;

use App\Enums\FinanceEntityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FinanceEntity extends Model
{
    /** @use HasFactory<\Database\Factories\FinanceEntityFactory> */
    use HasFactory;

    /**
     * Slug reserved for the default FAMILY entity that will back
     * FinanceContext::PRIBADI in a later migration task.
     */
    public const DEFAULT_SLUG_PRIBADI = 'pribadi';

    /**
     * Slug reserved for the default BUSINESS entity that will back
     * FinanceContext::USAHA_KEBUN in a later migration task.
     */
    public const DEFAULT_SLUG_USAHA_KEBUN = 'usaha-kebun';

    /**
     * public_id is generated internally and must never come from user input.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FinanceEntityType::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FinanceEntity $entity): void {
            if (blank($entity->name)) {
                throw new InvalidArgumentException('FinanceEntity name is required.');
            }

            if (! $entity->type instanceof FinanceEntityType) {
                throw new InvalidArgumentException('FinanceEntity type must be FAMILY or BUSINESS.');
            }

            if (blank($entity->public_id)) {
                $entity->public_id = static::generatePublicId();
            }

            if (blank($entity->slug)) {
                $entity->slug = static::uniqueSlugFromName((string) $entity->name);
            } else {
                $entity->slug = static::normalizeSlug((string) $entity->slug);
            }

            if ($entity->is_active === null) {
                $entity->is_active = true;
            }
        });
    }

    /**
     * Public URL identifier. Incremental id stays internal.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function plantationIntegration(): HasOne
    {
        return $this->hasOne(PlantationIntegration::class);
    }

    public function plantationOperatingBudgets(): HasMany
    {
        return $this->hasMany(PlantationOperatingBudget::class);
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(FinanceEntityAccessToken::class);
    }

    public function portalAccessGrants(): HasMany
    {
        return $this->hasMany(PortalAccessGrant::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(Budget::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(Receivable::class);
    }

    public function savingsGoals(): HasMany
    {
        return $this->hasMany(SavingsGoal::class);
    }

    public function recurringTransactions(): HasMany
    {
        return $this->hasMany(RecurringTransaction::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(FinanceAccount::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(FinanceTransfer::class);
    }

    public function capitalContributionsGiven(): HasMany
    {
        return $this->hasMany(BusinessCapitalContribution::class, 'source_entity_id');
    }

    public function capitalContributionsReceived(): HasMany
    {
        return $this->hasMany(BusinessCapitalContribution::class, 'business_entity_id');
    }

    public function ownerWithdrawalsGiven(): HasMany
    {
        return $this->hasMany(OwnerWithdrawal::class, 'business_entity_id');
    }

    public function ownerWithdrawalsReceived(): HasMany
    {
        return $this->hasMany(OwnerWithdrawal::class, 'family_entity_id');
    }

    public function profitDistributionsGiven(): HasMany
    {
        return $this->hasMany(ProfitDistribution::class, 'business_entity_id');
    }

    public function profitDistributionsReceived(): HasMany
    {
        return $this->hasMany(ProfitDistribution::class, 'family_entity_id');
    }

    public function defaultAccount(): ?FinanceAccount
    {
        return $this->accounts()
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    public function activeAccounts(): HasMany
    {
        return $this->accounts()->where('is_active', true)->orderByDesc('is_default')->orderBy('name');
    }

    public function isFamily(): bool
    {
        return $this->type === FinanceEntityType::FAMILY;
    }

    public function isBusiness(): bool
    {
        return $this->type === FinanceEntityType::BUSINESS;
    }

    public function hasActivePlantationIntegration(): bool
    {
        if (! $this->isBusiness()) {
            return false;
        }

        $this->loadMissing('plantationIntegration');

        return $this->plantationIntegration?->isActive() === true;
    }

    public function legacyContext(): string
    {
        return \App\Support\FinanceOwnership::contextFor($this);
    }

    public function hasFinancialRecords(): bool
    {
        return $this->transactions()->exists()
            || $this->incomes()->exists()
            || $this->budgets()->exists()
            || $this->debts()->exists()
            || $this->receivables()->exists()
            || $this->savingsGoals()->exists()
            || $this->recurringTransactions()->exists()
            || $this->categories()->exists()
            || $this->accounts()->exists()
            || $this->transfers()->exists()
            || $this->capitalContributionsGiven()->exists()
            || $this->capitalContributionsReceived()->exists()
            || $this->ownerWithdrawalsGiven()->exists()
            || $this->ownerWithdrawalsReceived()->exists()
            || $this->profitDistributionsGiven()->exists()
            || $this->profitDistributionsReceived()->exists();
    }

    public static function generatePublicId(): string
    {
        do {
            $publicId = (string) Str::ulid();
        } while (static::query()->where('public_id', $publicId)->exists());

        return $publicId;
    }

    public static function uniqueSlugFromName(string $name): string
    {
        $base = static::normalizeSlug($name);
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public static function normalizeSlug(string $value): string
    {
        $slug = Str::slug($value);

        return $slug !== '' ? $slug : 'entity';
    }
}
