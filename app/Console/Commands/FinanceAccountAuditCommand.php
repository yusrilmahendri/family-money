<?php

namespace App\Console\Commands;

use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\FinanceEntity;
use App\Services\BusinessCapitalContributionService;
use App\Services\FinanceAccountMovementMigrator;
use App\Services\FinanceAccountService;
use App\Services\FinanceTransferService;
use App\Services\OwnerWithdrawalService;
use App\Services\ProfitDistributionService;
use App\Services\ReceivableService;
use Illuminate\Console\Command;

class FinanceAccountAuditCommand extends Command
{
    protected $signature = 'finance:account-audit';

    protected $description = 'Read-only audit of finance accounts, defaults, and movement ownership';

    public function handle(
        FinanceAccountService $accounts,
        FinanceAccountMovementMigrator $movements,
        FinanceTransferService $transfers,
        BusinessCapitalContributionService $capital,
        OwnerWithdrawalService $withdrawals,
        ProfitDistributionService $distributions,
        ReceivableService $receivables
    ): int {
        $audit = $accounts->audit();
        $movement = $movements->audit();
        $transfer = $transfers->audit();
        $capitalAudit = $capital->audit();
        $withdrawalAudit = $withdrawals->audit();
        $distributionAudit = $distributions->audit();
        $receivableAudit = $receivables->audit();

        $this->info('Finance Account Audit');
        $this->newLine();

        $this->line('Entities without accounts: '.$audit['entities_without_accounts']->count());
        foreach ($audit['entities_without_accounts'] as $entity) {
            $this->line('  - '.$entity->name.' ('.$entity->slug.')');
        }

        $this->line('Entities with multiple defaults: '.$audit['multiple_defaults']->count());
        foreach ($audit['multiple_defaults'] as $row) {
            $this->line('  - entity_id '.$row->finance_entity_id.' ('.$row->total.')');
        }

        $this->line('Active accounts without default: '.$audit['active_without_default']->count());
        foreach ($audit['active_without_default'] as $entity) {
            $this->line('  - '.$entity->name.' ('.$entity->slug.')');
        }

        $this->line('Invalid entity relations: '.$audit['invalid_entity_relation']->count());
        $this->line('Duplicate account names: '.$audit['duplicate_names']->count());
        foreach ($audit['duplicate_names'] as $row) {
            $this->line('  - entity_id '.$row->finance_entity_id.' / '.$row->name.' ('.$row->total.')');
        }

        $this->newLine();
        $this->table(
            ['Check', 'Count'],
            [
                ['Entities without accounts', $audit['entities_without_accounts']->count()],
                ['Multiple defaults', $audit['multiple_defaults']->count()],
                ['Active accounts without default', $audit['active_without_default']->count()],
                ['Invalid entity relations', $audit['invalid_entity_relation']->count()],
                ['Duplicate account names', $audit['duplicate_names']->count()],
            ]
        );

        $this->newLine();
        $this->info('Finance Account Movement Audit');
        $this->newLine();

        $this->line('Financial records without account:');
        foreach ($movement['movements_without_account'] as $table => $count) {
            $this->line('  '.$table.': '.$count);
        }

        $this->line('Account entity mismatch:');
        foreach ($movement['account_entity_mismatch'] as $table => $count) {
            $this->line('  '.$table.': '.$count);
        }

        $this->line('Recurring account mismatch: '.$movement['recurring_account_mismatch']);
        $this->line('Movements on inactive accounts:');
        foreach ($movement['movements_on_inactive_accounts'] as $table => $count) {
            $this->line('  '.$table.': '.$count);
        }

        $this->newLine();
        $this->table(
            ['Movement check', 'Count'],
            [
                ['Records without account', array_sum($movement['movements_without_account'])],
                ['Account entity mismatch', array_sum($movement['account_entity_mismatch'])],
                ['Recurring account mismatch', $movement['recurring_account_mismatch']],
                ['Movements on inactive accounts', array_sum($movement['movements_on_inactive_accounts'])],
            ]
        );

        $this->newLine();
        $this->info('Finance Transfer Audit');
        $this->newLine();
        $this->line('Source/destination not the same entity: '.$transfer['cross_entity_accounts']);
        $this->line('Source equals destination: '.$transfer['same_source_and_destination']);
        $this->line('Invalid account relation: '.$transfer['invalid_account_relation']);
        $this->line('Transfer amount <= 0: '.$transfer['non_positive_amount']);
        $this->line('Orphan transfers: '.$transfer['orphan_transfers']);

        $this->newLine();
        $this->table(
            ['Transfer check', 'Count'],
            [
                ['Cross-entity accounts', $transfer['cross_entity_accounts']],
                ['Source equals destination', $transfer['same_source_and_destination']],
                ['Invalid account relation', $transfer['invalid_account_relation']],
                ['Non-positive amount', $transfer['non_positive_amount']],
                ['Orphan transfers', $transfer['orphan_transfers']],
            ]
        );

        $this->newLine();
        $this->info('Business Capital Audit');
        $this->newLine();
        $this->line('Source is not FAMILY: '.$capitalAudit['source_not_family']);
        $this->line('Destination is not BUSINESS: '.$capitalAudit['destination_not_business']);
        $this->line('Account entity mismatch: '.$capitalAudit['account_entity_mismatch']);
        $this->line('Invalid account relation: '.$capitalAudit['invalid_account_relation']);
        $this->line('Capital amount <= 0: '.$capitalAudit['non_positive_amount']);
        $this->line('Source equals destination: '.$capitalAudit['same_source_and_destination']);
        $this->line('Orphan capital records: '.$capitalAudit['orphan_contributions']);

        $this->newLine();
        $this->table(
            ['Capital check', 'Count'],
            [
                ['Source not FAMILY', $capitalAudit['source_not_family']],
                ['Destination not BUSINESS', $capitalAudit['destination_not_business']],
                ['Account entity mismatch', $capitalAudit['account_entity_mismatch']],
                ['Invalid account relation', $capitalAudit['invalid_account_relation']],
                ['Non-positive amount', $capitalAudit['non_positive_amount']],
                ['Source equals destination', $capitalAudit['same_source_and_destination']],
                ['Orphan contributions', $capitalAudit['orphan_contributions']],
            ]
        );

        $this->newLine();
        $this->info('Owner Withdrawal Audit');
        $this->newLine();
        $this->line('Source is not BUSINESS: '.$withdrawalAudit['source_not_business']);
        $this->line('Destination is not FAMILY: '.$withdrawalAudit['destination_not_family']);
        $this->line('Account entity mismatch: '.$withdrawalAudit['account_entity_mismatch']);
        $this->line('Invalid account relation: '.$withdrawalAudit['invalid_account_relation']);
        $this->line('Withdrawal amount <= 0: '.$withdrawalAudit['non_positive_amount']);
        $this->line('Source equals destination: '.$withdrawalAudit['same_source_and_destination']);
        $this->line('Orphan withdrawal records: '.$withdrawalAudit['orphan_withdrawals']);

        $this->newLine();
        $this->table(
            ['Withdrawal check', 'Count'],
            [
                ['Source not BUSINESS', $withdrawalAudit['source_not_business']],
                ['Destination not FAMILY', $withdrawalAudit['destination_not_family']],
                ['Account entity mismatch', $withdrawalAudit['account_entity_mismatch']],
                ['Invalid account relation', $withdrawalAudit['invalid_account_relation']],
                ['Non-positive amount', $withdrawalAudit['non_positive_amount']],
                ['Source equals destination', $withdrawalAudit['same_source_and_destination']],
                ['Orphan withdrawals', $withdrawalAudit['orphan_withdrawals']],
            ]
        );

        $this->newLine();
        $this->info('Profit Distribution Audit');
        $this->newLine();
        $this->line('Source is not BUSINESS: '.$distributionAudit['source_not_business']);
        $this->line('Destination is not FAMILY: '.$distributionAudit['destination_not_family']);
        $this->line('Account entity mismatch: '.$distributionAudit['account_entity_mismatch']);
        $this->line('Invalid account relation: '.$distributionAudit['invalid_account_relation']);
        $this->line('Distribution amount <= 0: '.$distributionAudit['non_positive_amount']);
        $this->line('Source equals destination: '.$distributionAudit['same_source_and_destination']);
        $this->line('Invalid period: '.$distributionAudit['invalid_period']);
        $this->line('Exceeds period profit: '.$distributionAudit['exceeds_period_profit']);
        $this->line('Exceeds all-time profit: '.$distributionAudit['exceeds_all_time_profit']);
        $this->line('Orphan distribution records: '.$distributionAudit['orphan_distributions']);

        $this->newLine();
        $this->table(
            ['Distribution check', 'Count'],
            [
                ['Source not BUSINESS', $distributionAudit['source_not_business']],
                ['Destination not FAMILY', $distributionAudit['destination_not_family']],
                ['Account entity mismatch', $distributionAudit['account_entity_mismatch']],
                ['Invalid account relation', $distributionAudit['invalid_account_relation']],
                ['Non-positive amount', $distributionAudit['non_positive_amount']],
                ['Source equals destination', $distributionAudit['same_source_and_destination']],
                ['Invalid period', $distributionAudit['invalid_period']],
                ['Exceeds period profit', $distributionAudit['exceeds_period_profit']],
                ['Exceeds all-time profit', $distributionAudit['exceeds_all_time_profit']],
                ['Orphan distributions', $distributionAudit['orphan_distributions']],
            ]
        );

        $debtAudit = $this->debtAudit();

        $this->newLine();
        $this->info('Debt Audit');
        $this->newLine();
        $this->line('Negative remaining: '.$debtAudit['negative_remaining']);
        $this->line('Remaining exceeds principal: '.$debtAudit['remaining_exceeds_principal']);
        $this->line('Payment exceeds principal: '.$debtAudit['payment_exceeds_principal']);
        $this->line('Orphan debt records: '.$debtAudit['orphan_debts']);
        $this->line('Orphan payment records: '.$debtAudit['orphan_payments']);

        $this->newLine();
        $this->table(
            ['Debt check', 'Count'],
            [
                ['Negative remaining', $debtAudit['negative_remaining']],
                ['Remaining exceeds principal', $debtAudit['remaining_exceeds_principal']],
                ['Payment exceeds principal', $debtAudit['payment_exceeds_principal']],
                ['Orphan debts', $debtAudit['orphan_debts']],
                ['Orphan payments', $debtAudit['orphan_payments']],
            ]
        );

        $this->newLine();
        $this->info('Receivable Audit');
        $this->newLine();
        $this->line('Negative remaining: '.$receivableAudit['negative_remaining']);
        $this->line('Remaining exceeds principal: '.$receivableAudit['remaining_exceeds_principal']);
        $this->line('Payment total mismatch: '.$receivableAudit['payment_mismatch']);
        $this->line('Account entity mismatch: '.$receivableAudit['account_entity_mismatch']);
        $this->line('Invalid account relation: '.$receivableAudit['invalid_account_relation']);
        $this->line('Invalid status: '.$receivableAudit['invalid_status']);
        $this->line('Unmarked overdue: '.$receivableAudit['unmarked_overdue']);
        $this->line('Orphan receivable records: '.$receivableAudit['orphan_receivables']);
        $this->line('Orphan payment records: '.$receivableAudit['orphan_payments']);

        $this->newLine();
        $this->table(
            ['Receivable check', 'Count'],
            [
                ['Negative remaining', $receivableAudit['negative_remaining']],
                ['Remaining exceeds principal', $receivableAudit['remaining_exceeds_principal']],
                ['Payment mismatch', $receivableAudit['payment_mismatch']],
                ['Account entity mismatch', $receivableAudit['account_entity_mismatch']],
                ['Invalid account relation', $receivableAudit['invalid_account_relation']],
                ['Invalid status', $receivableAudit['invalid_status']],
                ['Unmarked overdue', $receivableAudit['unmarked_overdue']],
                ['Orphan receivables', $receivableAudit['orphan_receivables']],
                ['Orphan payments', $receivableAudit['orphan_payments']],
            ]
        );

        if (
            $accounts->hasCriticalInconsistencies()
            || $movements->hasCriticalInconsistencies()
            || $transfers->hasCriticalInconsistencies()
            || $capital->hasCriticalInconsistencies()
            || $withdrawals->hasCriticalInconsistencies()
            || $distributions->hasCriticalInconsistencies()
            || $receivables->hasCriticalInconsistencies()
            || array_sum($debtAudit) > 0
        ) {
            $this->error('Critical account inconsistency found.');

            return self::FAILURE;
        }

        $this->info('Finance accounts are consistent.');

        return self::SUCCESS;
    }

    /**
     * @return array{negative_remaining: int, remaining_exceeds_principal: int, payment_exceeds_principal: int, orphan_debts: int, orphan_payments: int}
     */
    private function debtAudit(): array
    {
        $validEntityIds = FinanceEntity::query()->pluck('id');
        $validDebtIds = Debt::query()->pluck('id');
        $paymentExceedsPrincipal = 0;

        Debt::query()->withSum('payments', 'amount')->each(function (Debt $debt) use (&$paymentExceedsPrincipal): void {
            $paid = (float) ($debt->payments_sum_amount ?? 0);

            if (round($paid, 2) > round((float) $debt->principal_total, 2)) {
                $paymentExceedsPrincipal++;
            }
        });

        return [
            'negative_remaining' => Debt::query()->where('remaining_balance', '<', 0)->count(),
            'remaining_exceeds_principal' => Debt::query()
                ->whereColumn('remaining_balance', '>', 'principal_total')
                ->count(),
            'payment_exceeds_principal' => $paymentExceedsPrincipal,
            'orphan_debts' => Debt::query()
                ->where(function ($query) use ($validEntityIds): void {
                    $query->whereNull('finance_entity_id')
                        ->orWhereNotIn('finance_entity_id', $validEntityIds);
                })
                ->count(),
            'orphan_payments' => DebtPayment::query()
                ->where(function ($query) use ($validDebtIds): void {
                    $query->whereNull('debt_id')
                        ->orWhereNotIn('debt_id', $validDebtIds);
                })
                ->count(),
        ];
    }
}
