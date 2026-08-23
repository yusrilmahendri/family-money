<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\FinanceAccountType;
use App\Enums\FinanceEntityType;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FinanceAccountService
{
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
