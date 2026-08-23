<?php

namespace App\Services;

use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinanceAccountMovementMigrator
{
    /**
     * @return list<string>
     */
    public static function ownedMovementTables(): array
    {
        return [
            'transactions',
            'incomes',
            'recurring_transactions',
        ];
    }

    /**
     * @return array<string, array{parent: string, fk: string}>
     */
    public static function childMovementTables(): array
    {
        return [
            'debt_payments' => ['parent' => 'debts', 'fk' => 'debt_id'],
            'receivable_payments' => ['parent' => 'receivables', 'fk' => 'receivable_id'],
            'goal_contributions' => ['parent' => 'savings_goals', 'fk' => 'savings_goal_id'],
            'budget_activities' => ['parent' => 'budgets', 'fk' => 'budget_id'],
        ];
    }

    /**
     * Idempotent mapping of existing money movements onto each entity's default account.
     *
     * @return array<string, array{total: int, mapped: int, unmapped: int}>
     */
    public function backfill(): array
    {
        app(FinanceAccountService::class)->provisionMissingDefaults();

        $defaultIds = $this->defaultAccountIds();

        foreach ($defaultIds as $entityId => $accountId) {
            foreach (self::ownedMovementTables() as $table) {
                if (! $this->hasAccountColumn($table)) {
                    continue;
                }

                DB::table($table)
                    ->where('finance_entity_id', $entityId)
                    ->whereNull('finance_account_id')
                    ->update(['finance_account_id' => $accountId]);
            }

            foreach (self::childMovementTables() as $table => $meta) {
                if (! $this->hasAccountColumn($table) || ! Schema::hasTable($meta['parent'])) {
                    continue;
                }

                $parentIds = DB::table($meta['parent'])
                    ->where('finance_entity_id', $entityId)
                    ->pluck('id');

                if ($parentIds->isEmpty()) {
                    continue;
                }

                DB::table($table)
                    ->whereIn($meta['fk'], $parentIds)
                    ->whereNull('finance_account_id')
                    ->update(['finance_account_id' => $accountId]);
            }
        }

        return $this->counts();
    }

    /**
     * @return array<int, int>
     */
    public function defaultAccountIds(): array
    {
        $ids = [];

        FinanceEntity::query()
            ->orderBy('id')
            ->each(function (FinanceEntity $entity) use (&$ids): void {
                $account = $entity->accounts()
                    ->orderByDesc('is_default')
                    ->orderBy('id')
                    ->first();

                if ($account instanceof FinanceAccount) {
                    $ids[(int) $entity->id] = (int) $account->id;
                }
            });

        return $ids;
    }

    /**
     * @return array<string, array{total: int, mapped: int, unmapped: int}>
     */
    public function counts(): array
    {
        $result = [];

        foreach (self::ownedMovementTables() as $table) {
            $result[$table] = $this->tableCounts($table);
        }

        foreach (array_keys(self::childMovementTables()) as $table) {
            $result[$table] = $this->tableCounts($table);
        }

        return $result;
    }

    /**
     * @return array{
     *     movements_without_account: array<string, int>,
     *     account_entity_mismatch: array<string, int>,
     *     recurring_account_mismatch: int,
     *     movements_on_inactive_accounts: array<string, int>
     * }
     */
    public function audit(): array
    {
        return [
            'movements_without_account' => $this->unmappedCounts(),
            'account_entity_mismatch' => $this->mismatchCounts(),
            'recurring_account_mismatch' => $this->recurringMismatchCount(),
            'movements_on_inactive_accounts' => $this->inactiveUsageCounts(),
        ];
    }

    public function hasCriticalInconsistencies(): bool
    {
        $audit = $this->audit();

        return array_sum($audit['movements_without_account']) > 0
            || array_sum($audit['account_entity_mismatch']) > 0
            || $audit['recurring_account_mismatch'] > 0;
    }

    public function unmappedTotal(): int
    {
        return array_sum($this->unmappedCounts());
    }

    /**
     * @return array<string, int>
     */
    private function unmappedCounts(): array
    {
        $counts = [];

        foreach (array_keys($this->counts()) as $table) {
            $counts[$table] = $this->tableCounts($table)['unmapped'];
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function mismatchCounts(): array
    {
        $counts = [];

        foreach (self::ownedMovementTables() as $table) {
            $counts[$table] = $this->ownedMismatchCount($table);
        }

        foreach (self::childMovementTables() as $table => $meta) {
            $counts[$table] = $this->childMismatchCount($table, $meta['parent'], $meta['fk']);
        }

        return $counts;
    }

    private function recurringMismatchCount(): int
    {
        if (! $this->hasAccountColumn('recurring_transactions')) {
            return 0;
        }

        return (int) DB::table('recurring_transactions as r')
            ->leftJoin('finance_accounts as a', 'a.id', '=', 'r.finance_account_id')
            ->whereNotNull('r.finance_account_id')
            ->where(function ($query): void {
                $query->whereNull('a.id')
                    ->orWhereColumn('a.finance_entity_id', '!=', 'r.finance_entity_id');
            })
            ->count();
    }

    /**
     * @return array<string, int>
     */
    private function inactiveUsageCounts(): array
    {
        $counts = [];

        foreach (self::ownedMovementTables() as $table) {
            $counts[$table] = $this->inactiveOwnedCount($table);
        }

        foreach (array_keys(self::childMovementTables()) as $table) {
            $counts[$table] = $this->inactiveChildCount($table);
        }

        return $counts;
    }

    /**
     * @return array{total: int, mapped: int, unmapped: int}
     */
    private function tableCounts(string $table): array
    {
        if (! $this->hasAccountColumn($table)) {
            return ['total' => 0, 'mapped' => 0, 'unmapped' => 0];
        }

        $total = (int) DB::table($table)->count();
        $mapped = (int) DB::table($table)->whereNotNull('finance_account_id')->count();

        return [
            'total' => $total,
            'mapped' => $mapped,
            'unmapped' => $total - $mapped,
        ];
    }

    private function ownedMismatchCount(string $table): int
    {
        if (! $this->hasAccountColumn($table)) {
            return 0;
        }

        return (int) DB::table($table)
            ->join('finance_accounts', 'finance_accounts.id', '=', $table.'.finance_account_id')
            ->whereColumn('finance_accounts.finance_entity_id', '!=', $table.'.finance_entity_id')
            ->count();
    }

    private function childMismatchCount(string $table, string $parent, string $fk): int
    {
        if (! $this->hasAccountColumn($table) || ! Schema::hasTable($parent)) {
            return 0;
        }

        return (int) DB::table($table)
            ->join($parent, $parent.'.id', '=', $table.'.'.$fk)
            ->join('finance_accounts', 'finance_accounts.id', '=', $table.'.finance_account_id')
            ->whereColumn('finance_accounts.finance_entity_id', '!=', $parent.'.finance_entity_id')
            ->count();
    }

    private function inactiveOwnedCount(string $table): int
    {
        if (! $this->hasAccountColumn($table)) {
            return 0;
        }

        return (int) DB::table($table)
            ->join('finance_accounts', 'finance_accounts.id', '=', $table.'.finance_account_id')
            ->where('finance_accounts.is_active', false)
            ->count();
    }

    private function inactiveChildCount(string $table): int
    {
        if (! $this->hasAccountColumn($table)) {
            return 0;
        }

        return (int) DB::table($table)
            ->join('finance_accounts', 'finance_accounts.id', '=', $table.'.finance_account_id')
            ->where('finance_accounts.is_active', false)
            ->count();
    }

    private function hasAccountColumn(string $table): bool
    {
        return Schema::hasTable($table) && Schema::hasColumn($table, 'finance_account_id');
    }
}
