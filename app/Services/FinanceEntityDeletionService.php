<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\BusinessCapitalContribution;
use App\Models\Category;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\FinanceEntityAccessToken;
use App\Models\FinanceTransfer;
use App\Models\GoalContribution;
use App\Models\Income;
use App\Models\OwnerWithdrawal;
use App\Models\PlantationIntegration;
use App\Models\PlantationOperatingBudget;
use App\Models\ProfitDistribution;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\RecurringTransaction;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class FinanceEntityDeletionService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Permanently delete a finance entity and every record it owns or
     * participates in as source/destination. Atomic: any failure rolls back.
     *
     * @throws Throwable
     */
    public function delete(FinanceEntity $entity): void
    {
        DB::transaction(function () use ($entity): void {
            $locked = FinanceEntity::query()->whereKey($entity->id)->lockForUpdate()->firstOrFail();
            $summary = $this->deletionSummary($locked);

            $this->audit->record(
                $locked,
                AuditAction::FINANCE_ENTITY_DELETED,
                null,
                $summary,
            );

            $this->detachAuditLogs((int) $locked->id);
            $this->purgeOwnedRecords($locked);

            $locked->delete();
        });
    }

    /**
     * Safe counts and identity for the immutable audit trail.
     *
     * @return array<string, mixed>
     */
    public function deletionSummary(FinanceEntity $entity): array
    {
        $accountIds = $entity->accounts()->pluck('id');

        return [
            'public_id' => $entity->public_id,
            'name' => $entity->name,
            'type' => $entity->type?->value ?? (string) $entity->type,
            'slug' => $entity->slug,
            'accounts_count' => $accountIds->count(),
            'transactions_count' => $entity->transactions()->count(),
            'incomes_count' => $entity->incomes()->count(),
            'budgets_count' => $entity->budgets()->count(),
            'debts_count' => $entity->debts()->count(),
            'savings_goals_count' => $entity->savingsGoals()->count(),
            'receivables_count' => $entity->receivables()->count(),
            'transfers_count' => $entity->transfers()->count(),
            'categories_count' => $entity->categories()->count(),
            'recurring_count' => $entity->recurringTransactions()->count(),
            'access_tokens_count' => $entity->accessTokens()->count(),
            'capital_contributions_count' => $this->relatedCapitalQuery($entity)->count(),
            'owner_withdrawals_count' => $this->relatedWithdrawalQuery($entity)->count(),
            'profit_distributions_count' => $this->relatedDistributionQuery($entity)->count(),
        ];
    }

    private function detachAuditLogs(int $entityId): void
    {
        DB::table('audit_logs')
            ->where('finance_entity_id', $entityId)
            ->update(['finance_entity_id' => null]);
    }

    private function purgeOwnedRecords(FinanceEntity $entity): void
    {
        $entityId = (int) $entity->id;
        $accountIds = $entity->accounts()->pluck('id');
        $transactionIds = $entity->transactions()->pluck('id');
        $budgetIds = $entity->budgets()->pluck('id');
        $debtIds = $entity->debts()->pluck('id');
        $goalIds = $entity->savingsGoals()->pluck('id');
        $receivableIds = $entity->receivables()->pluck('id');

        $this->deleteTransactionItems($transactionIds);

        $this->deleteByParentOrAccount(DebtPayment::query(), 'debt_id', $debtIds, $accountIds);
        $this->deleteByParentOrAccount(GoalContribution::query(), 'savings_goal_id', $goalIds, $accountIds);
        $this->deleteByParentOrAccount(BudgetActivity::query(), 'budget_id', $budgetIds, $accountIds);
        $this->deleteByParentOrAccount(ReceivablePayment::query(), 'receivable_id', $receivableIds, $accountIds);

        $this->relatedCapitalQuery($entity)->delete();
        $this->relatedWithdrawalQuery($entity)->delete();
        $this->relatedDistributionQuery($entity)->delete();

        FinanceTransfer::query()->where(function ($query) use ($entityId, $accountIds): void {
            $query->where('finance_entity_id', $entityId);

            if ($accountIds->isNotEmpty()) {
                $query->orWhereIn('source_account_id', $accountIds)
                    ->orWhereIn('destination_account_id', $accountIds);
            }
        })->delete();

        Transaction::query()->where('finance_entity_id', $entityId)->delete();
        Income::query()->where('finance_entity_id', $entityId)->delete();
        RecurringTransaction::query()->where('finance_entity_id', $entityId)->delete();
        Receivable::query()->where('finance_entity_id', $entityId)->delete();
        Debt::query()->where('finance_entity_id', $entityId)->delete();
        SavingsGoal::query()->where('finance_entity_id', $entityId)->delete();
        Budget::query()->where('finance_entity_id', $entityId)->delete();
        Category::query()->where('finance_entity_id', $entityId)->delete();

        FinanceEntityAccessToken::query()->where('finance_entity_id', $entityId)->delete();
        PlantationOperatingBudget::query()->where('finance_entity_id', $entityId)->delete();
        PlantationIntegration::query()->where('finance_entity_id', $entityId)->delete();
        FinanceAccount::query()->where('finance_entity_id', $entityId)->delete();
    }

    /**
     * @param  Collection<int, int|string>  $transactionIds
     */
    private function deleteTransactionItems(Collection $transactionIds): void
    {
        if ($transactionIds->isEmpty() || ! Schema::hasTable('transaction_items')) {
            return;
        }

        DB::table('transaction_items')->whereIn('transaction_id', $transactionIds)->delete();
    }

    /**
     * @param  Collection<int, int|string>  $parentIds
     * @param  Collection<int, int|string>  $accountIds
     */
    private function deleteByParentOrAccount($query, string $parentColumn, Collection $parentIds, Collection $accountIds): void
    {
        $query->where(function ($inner) use ($parentColumn, $parentIds, $accountIds): void {
            if ($parentIds->isNotEmpty()) {
                $inner->whereIn($parentColumn, $parentIds);
            }

            if ($accountIds->isNotEmpty()) {
                $inner->orWhereIn('finance_account_id', $accountIds);
            }

            if ($parentIds->isEmpty() && $accountIds->isEmpty()) {
                $inner->whereRaw('0 = 1');
            }
        })->delete();
    }

    private function relatedCapitalQuery(FinanceEntity $entity)
    {
        $id = (int) $entity->id;

        return BusinessCapitalContribution::query()->where(function ($query) use ($id): void {
            $query->where('source_entity_id', $id)->orWhere('business_entity_id', $id);
        });
    }

    private function relatedWithdrawalQuery(FinanceEntity $entity)
    {
        $id = (int) $entity->id;

        return OwnerWithdrawal::query()->where(function ($query) use ($id): void {
            $query->where('business_entity_id', $id)->orWhere('family_entity_id', $id);
        });
    }

    private function relatedDistributionQuery(FinanceEntity $entity)
    {
        $id = (int) $entity->id;

        return ProfitDistribution::query()->where(function ($query) use ($id): void {
            $query->where('business_entity_id', $id)->orWhere('family_entity_id', $id);
        });
    }
}
