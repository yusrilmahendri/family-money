<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\FinanceAccountType;
use App\Enums\FinanceEntityType;
use App\Models\BudgetActivity;
use App\Models\BusinessCapitalContribution;
use App\Models\DebtPayment;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\FinanceTransfer;
use App\Models\GoalContribution;
use App\Models\Income;
use App\Models\OwnerWithdrawal;
use App\Models\ProfitDistribution;
use App\Models\ReceivablePayment;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FinanceAccountService
{
    public const DELETE_BLOCKED_HISTORY = 'Rekening tidak dapat dihapus karena sudah memiliki histori transaksi. Gunakan fitur Nonaktifkan agar histori keuangan tetap terjaga.';

    public const DELETE_BLOCKED_DEFAULT = 'Rekening default tidak dapat dihapus. Tetapkan rekening lain sebagai default terlebih dahulu.';

    public const DELETE_BLOCKED_SOLE = 'Rekening ini tidak dapat dihapus karena merupakan satu-satunya rekening pada entitas.';

    public const DELETE_TOOLTIP_HISTORY = 'Tidak dapat dihapus karena memiliki histori transaksi.';

    public function __construct(private readonly AuditLogService $audit) {}

    public function defaultNameFor(FinanceEntity $entity): string
    {
        return $entity->type === FinanceEntityType::BUSINESS
            ? 'Kas Utama Usaha'
            : 'Kas Utama Keluarga';
    }

    public function ensureDefaultAccount(FinanceEntity $entity): FinanceAccount
    {
        $existing = $entity->accounts()->orderBy('id')->first();

        if ($existing instanceof FinanceAccount) {
            return $existing;
        }

        return $this->create($entity, [
            'name' => $this->defaultNameFor($entity),
            'type' => FinanceAccountType::CASH,
            'opening_balance' => 0,
            'is_active' => true,
            'is_default' => true,
        ]);
    }

    public function provisionMissingDefaults(): int
    {
        $created = 0;

        FinanceEntity::query()
            ->whereDoesntHave('accounts')
            ->orderBy('id')
            ->each(function (FinanceEntity $entity) use (&$created): void {
                $this->ensureDefaultAccount($entity);
                $created++;
            });

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(FinanceEntity $entity, array $data): FinanceAccount
    {
        return DB::transaction(function () use ($entity, $data) {
            $locked = FinanceEntity::query()->whereKey($entity->id)->lockForUpdate()->firstOrFail();
            $isFirst = ! $locked->accounts()->exists();
            $isActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;

            if ($isFirst) {
                $isActive = true;
                $makeDefault = true;
            } else {
                $makeDefault = $isActive && (bool) ($data['is_default'] ?? false);
            }

            if ($makeDefault) {
                $this->clearDefault($locked);
            }

            $account = $locked->accounts()->create([
                'name' => $data['name'],
                'type' => $data['type'] instanceof FinanceAccountType
                    ? $data['type']
                    : FinanceAccountType::from((string) $data['type']),
                'bank_name' => $data['bank_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'description' => $data['description'] ?? null,
                'opening_balance' => $data['opening_balance'] ?? 0,
                'is_active' => $isActive,
                'is_default' => $makeDefault,
            ]);

            $this->audit->recordCreated($account, $locked);

            return $account;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(FinanceAccount $account, array $data): FinanceAccount
    {
        return DB::transaction(function () use ($account, $data) {
            $account->refresh();
            $old = $this->audit->snapshot($account);
            $entity = $account->financeEntity;
            $wasDefault = $account->is_default;
            $makeDefault = array_key_exists('is_default', $data)
                ? (bool) $data['is_default']
                : $account->is_default;

            if ($makeDefault && ! $account->is_active) {
                throw new InvalidArgumentException('Hanya account aktif yang dapat dijadikan default.');
            }

            if ($makeDefault) {
                $this->clearDefault($entity, $account->id);
            }

            $account->update([
                'name' => $data['name'],
                'type' => $data['type'] instanceof FinanceAccountType
                    ? $data['type']
                    : FinanceAccountType::from((string) $data['type']),
                'bank_name' => $data['bank_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'description' => $data['description'] ?? null,
                'opening_balance' => $data['opening_balance'] ?? $account->opening_balance,
                'is_default' => $makeDefault,
            ]);

            if ($wasDefault && ! $makeDefault) {
                $this->promoteReplacement($entity, $account->id);
            }

            $fresh = $account->fresh();
            $this->audit->recordUpdated($fresh, $old, $entity);

            return $fresh;
        });
    }

    public function activate(FinanceAccount $account): FinanceAccount
    {
        return DB::transaction(function () use ($account) {
            $account->refresh();
            $old = $this->audit->snapshot($account);
            $entity = $account->financeEntity;

            $account->update(['is_active' => true]);

            $hasDefault = $entity->accounts()
                ->where('is_active', true)
                ->where('is_default', true)
                ->exists();

            if (! $hasDefault) {
                $account->update(['is_default' => true]);
            }

            $fresh = $account->fresh();
            $this->audit->record($fresh, AuditAction::ACTIVATE, $entity, $old, $this->audit->snapshot($fresh));

            return $fresh;
        });
    }

    public function deactivate(FinanceAccount $account): FinanceAccount
    {
        return DB::transaction(function () use ($account) {
            $account->refresh();
            $old = $this->audit->snapshot($account);
            $entity = $account->financeEntity;
            $wasDefault = $account->is_default;

            $account->update([
                'is_active' => false,
                'is_default' => false,
            ]);

            if ($wasDefault) {
                $this->promoteReplacement($entity, $account->id);
            }

            $fresh = $account->fresh();
            $this->audit->record($fresh, AuditAction::DEACTIVATE, $entity, $old, $this->audit->snapshot($fresh));

            return $fresh;
        });
    }

    public function setDefault(FinanceAccount $account): FinanceAccount
    {
        if (! $account->is_active) {
            throw new InvalidArgumentException('Hanya account aktif yang dapat dijadikan default.');
        }

        return DB::transaction(function () use ($account) {
            $account->refresh();
            $old = $this->audit->snapshot($account);
            $this->clearDefault($account->financeEntity, $account->id);
            $account->update(['is_default' => true]);

            $fresh = $account->fresh();
            $this->audit->record($fresh, AuditAction::SET_DEFAULT, $account->financeEntity, $old, $this->audit->snapshot($fresh));

            return $fresh;
        });
    }

    public function hasFinancialHistory(FinanceAccount $account): bool
    {
        if ($this->hasNonZeroOpeningBalance($account)) {
            return true;
        }

        $accountId = (int) $account->id;

        foreach ($this->financialHistoryBindings() as [$model, $column]) {
            if ($model::query()->where($column, $accountId)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function canBeDeleted(FinanceAccount $account): bool
    {
        return $this->deleteBlockReason($account) === null;
    }

    public function deleteBlockReason(FinanceAccount $account, ?bool $hasHistory = null, ?bool $hasSiblingAccount = null): ?string
    {
        if ($hasHistory ?? $this->hasFinancialHistory($account)) {
            return self::DELETE_BLOCKED_HISTORY;
        }

        if ($account->is_default) {
            return self::DELETE_BLOCKED_DEFAULT;
        }

        $hasSibling = $hasSiblingAccount ?? FinanceAccount::query()
            ->where('finance_entity_id', $account->finance_entity_id)
            ->where('id', '!=', $account->id)
            ->exists();

        if (! $hasSibling) {
            return self::DELETE_BLOCKED_SOLE;
        }

        return null;
    }

    /**
     * @param  Collection<int, FinanceAccount>  $accounts
     */
    public function annotateDeletionEligibility(Collection $accounts): void
    {
        $historyIds = array_flip($this->accountIdsWithFinancialHistory($accounts));
        $hasSibling = $accounts->count() > 1;

        foreach ($accounts as $account) {
            $reason = $this->deleteBlockReason(
                $account,
                isset($historyIds[(int) $account->id]),
                $hasSibling,
            );

            $account->setAttribute('can_delete', $reason === null);
            $account->setAttribute('delete_disabled_title', match ($reason) {
                self::DELETE_BLOCKED_HISTORY => self::DELETE_TOOLTIP_HISTORY,
                self::DELETE_BLOCKED_DEFAULT => self::DELETE_BLOCKED_DEFAULT,
                self::DELETE_BLOCKED_SOLE => self::DELETE_BLOCKED_SOLE,
                default => null,
            });
        }
    }

    public function deleteAccount(FinanceEntity $entity, FinanceAccount $account): void
    {
        abort_unless((int) $account->finance_entity_id === (int) $entity->id, 404);

        DB::transaction(function () use ($entity, $account): void {
            $locked = FinanceAccount::query()
                ->whereKey($account->id)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless((int) $locked->finance_entity_id === (int) $entity->id, 404);

            $reason = $this->deleteBlockReason($locked);

            if ($reason !== null) {
                throw new InvalidArgumentException($reason);
            }

            $old = $this->audit->snapshot($locked);

            try {
                $locked->delete();
            } catch (QueryException) {
                throw new InvalidArgumentException(self::DELETE_BLOCKED_HISTORY);
            }

            $this->audit->recordDeleted($locked, $old, $entity);
        });
    }

    /**
     * @param  Collection<int, FinanceAccount>|iterable<int, FinanceAccount|int>  $accounts
     * @return list<int>
     */
    public function accountIdsWithFinancialHistory(iterable $accounts): array
    {
        $ids = [];
        $used = [];

        foreach ($accounts as $account) {
            $id = (int) (is_object($account) ? $account->id : $account);
            $ids[] = $id;

            if (is_object($account) && $this->hasNonZeroOpeningBalance($account)) {
                $used[$id] = true;
            }
        }

        $ids = array_values(array_unique($ids));

        if ($ids === []) {
            return [];
        }

        if ($used === []) {
            FinanceAccount::query()
                ->whereIn('id', $ids)
                ->where('opening_balance', '!=', 0)
                ->pluck('id')
                ->each(function ($id) use (&$used): void {
                    $used[(int) $id] = true;
                });
        }

        foreach ($this->financialHistoryBindings() as [$model, $column]) {
            $remaining = array_values(array_filter($ids, fn (int $id): bool => ! isset($used[$id])));

            if ($remaining === []) {
                break;
            }

            $model::query()
                ->whereIn($column, $remaining)
                ->distinct()
                ->pluck($column)
                ->each(function ($id) use (&$used): void {
                    $used[(int) $id] = true;
                });
        }

        return array_keys($used);
    }

    /**
     * @return list<array{0: class-string, 1: string}>
     */
    private function financialHistoryBindings(): array
    {
        return [
            [Transaction::class, 'finance_account_id'],
            [Income::class, 'finance_account_id'],
            [RecurringTransaction::class, 'finance_account_id'],
            [DebtPayment::class, 'finance_account_id'],
            [ReceivablePayment::class, 'finance_account_id'],
            [GoalContribution::class, 'finance_account_id'],
            [BudgetActivity::class, 'finance_account_id'],
            [FinanceTransfer::class, 'source_account_id'],
            [FinanceTransfer::class, 'destination_account_id'],
            [BusinessCapitalContribution::class, 'source_account_id'],
            [BusinessCapitalContribution::class, 'destination_account_id'],
            [OwnerWithdrawal::class, 'source_account_id'],
            [OwnerWithdrawal::class, 'destination_account_id'],
            [ProfitDistribution::class, 'source_account_id'],
            [ProfitDistribution::class, 'destination_account_id'],
        ];
    }

    private function hasNonZeroOpeningBalance(FinanceAccount $account): bool
    {
        return abs((float) $account->opening_balance) >= 0.01;
    }

    private function clearDefault(FinanceEntity $entity, ?int $exceptId = null): void
    {
        $query = $entity->accounts()->where('is_default', true);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['is_default' => false]);
    }

    private function promoteReplacement(FinanceEntity $entity, int $exceptId): void
    {
        $replacement = $entity->accounts()
            ->where('is_active', true)
            ->where('id', '!=', $exceptId)
            ->orderBy('id')
            ->lockForUpdate()
            ->first();

        if ($replacement instanceof FinanceAccount) {
            $replacement->update(['is_default' => true]);
        }
    }

    /**
     * @return array{
     *     entities_without_accounts: \Illuminate\Support\Collection<int, FinanceEntity>,
     *     multiple_defaults: \Illuminate\Support\Collection<int, object>,
     *     active_without_default: \Illuminate\Support\Collection<int, FinanceEntity>,
     *     invalid_entity_relation: \Illuminate\Support\Collection<int, FinanceAccount>,
     *     duplicate_names: \Illuminate\Support\Collection<int, object>
     * }
     */
    public function audit(): array
    {
        $validEntityIds = FinanceEntity::query()->pluck('id');

        return [
            'entities_without_accounts' => FinanceEntity::query()
                ->whereDoesntHave('accounts')
                ->orderBy('id')
                ->get(['id', 'name', 'slug', 'public_id', 'type']),
            'multiple_defaults' => FinanceAccount::query()
                ->select('finance_entity_id', DB::raw('COUNT(*) as total'))
                ->where('is_default', true)
                ->groupBy('finance_entity_id')
                ->having('total', '>', 1)
                ->get(),
            'active_without_default' => FinanceEntity::query()
                ->whereHas('accounts', fn ($query) => $query->where('is_active', true))
                ->whereDoesntHave('accounts', fn ($query) => $query->where('is_default', true))
                ->orderBy('id')
                ->get(['id', 'name', 'slug', 'public_id', 'type']),
            'invalid_entity_relation' => FinanceAccount::query()
                ->where(function ($query) use ($validEntityIds): void {
                    $query->whereNull('finance_entity_id')
                        ->orWhereNotIn('finance_entity_id', $validEntityIds);
                })
                ->get(),
            'duplicate_names' => FinanceAccount::query()
                ->select('finance_entity_id', 'name', DB::raw('COUNT(*) as total'))
                ->groupBy('finance_entity_id', 'name')
                ->having('total', '>', 1)
                ->get(),
        ];
    }

    public function hasCriticalInconsistencies(): bool
    {
        $audit = $this->audit();

        return $audit['entities_without_accounts']->isNotEmpty()
            || $audit['multiple_defaults']->isNotEmpty()
            || $audit['active_without_default']->isNotEmpty()
            || $audit['invalid_entity_relation']->isNotEmpty()
            || $audit['duplicate_names']->isNotEmpty();
    }
}
