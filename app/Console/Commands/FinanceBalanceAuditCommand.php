<?php

namespace App\Console\Commands;

use App\Models\BusinessCapitalContribution;
use App\Models\FinanceEntity;
use App\Models\OwnerWithdrawal;
use App\Models\ProfitDistribution;
use App\Models\Saldo;
use App\Services\FinanceAccountBalanceService;
use App\Services\FinanceAccountMovementMigrator;
use App\Services\SaldoGlobalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class FinanceBalanceAuditCommand extends Command
{
    protected $signature = 'finance:balance-audit';

    protected $description = 'Read-only reconciliation of account-based balances vs legacy global saldo';

    public function handle(
        FinanceAccountBalanceService $balances,
        SaldoGlobalService $legacy,
        FinanceAccountMovementMigrator $movements
    ): int {
        $this->info('Finance Account Balance Audit');
        $this->line('Read-only. Formula: opening + income + transfer_in + capital_in + withdrawal_in + distribution_in + receivable_in - transaction - debt_payment - goal_contribution - budget_activity - transfer_out - capital_out - withdrawal_out - distribution_out.');
        $this->line('Budget headers are not outflow. Transfers, capital, prive, profit distribution, and unpaid receivable principal are not income/expense. Inactive accounts are included. saldos are not used.');
        $this->newLine();

        $tableRows = [];
        $entityTotals = [];
        $newAll = 0.0;
        $openingAll = 0.0;
        $formulaMismatch = 0;
        $cross = [
            'capital_out' => 0.0,
            'capital_in' => 0.0,
            'withdrawal_out' => 0.0,
            'withdrawal_in' => 0.0,
            'distribution_out' => 0.0,
            'distribution_in' => 0.0,
        ];

        FinanceEntity::query()
            ->orderBy('id')
            ->get()
            ->each(function (FinanceEntity $entity) use ($balances, &$tableRows, &$entityTotals, &$newAll, &$openingAll, &$formulaMismatch, &$cross): void {
                $summary = $balances->summary($entity);
                $entityTotals[] = [$entity->name, $entity->slug, number_format($summary['total'], 2, '.', '')];
                $newAll += $summary['total'];

                foreach ($summary['rows'] as $row) {
                    $account = $row['account'];
                    $openingAll += $row['opening_balance'];
                    $derived = $balances->balance($account);

                    if (abs($derived - (float) $row['balance']) > 0.009) {
                        $formulaMismatch++;
                    }

                    $tableRows[] = [
                        $entity->name,
                        $account->name.($account->is_active ? '' : ' [inactive]'),
                        number_format($row['opening_balance'], 2, '.', ''),
                        number_format($row['inflow'], 2, '.', ''),
                        number_format($row['outflow'], 2, '.', ''),
                        number_format($row['balance'], 2, '.', ''),
                    ];
                }

                $cross['capital_out'] += (float) $summary['rows']->sum('capital_out');
                $cross['capital_in'] += (float) $summary['rows']->sum('capital_in');
                $cross['withdrawal_out'] += (float) $summary['rows']->sum('withdrawal_out');
                $cross['withdrawal_in'] += (float) $summary['rows']->sum('withdrawal_in');
                $cross['distribution_out'] += (float) $summary['rows']->sum('distribution_out');
                $cross['distribution_in'] += (float) $summary['rows']->sum('distribution_in');
            });

        $this->table(
            ['Entity', 'Account', 'Opening Balance', 'Inflow', 'Outflow', 'New Balance'],
            $tableRows
        );

        $this->newLine();
        $this->info('Entity totals (account-based)');
        $this->table(['Entity', 'Slug', 'New Account-Based Balance'], $entityTotals);

        $legacyBalance = $legacy->getSaldoGlobal();
        $legacyBreakdown = $legacy->getBreakdown();
        $manualSaldos = $this->manualSaldoTotal();
        $unmapped = $movements->unmappedTotal();
        $difference = $legacyBalance - $newAll;

        $this->newLine();
        $this->info('Legacy reconciliation (default entities + global)');
        $this->line('Legacy Global Balance: '.number_format($legacyBalance, 2, '.', ''));
        $this->line('New Account-Based Balance (all entities): '.number_format($newAll, 2, '.', ''));
        $this->line('Difference (legacy − new): '.number_format($difference, 2, '.', ''));

        foreach ([FinanceEntity::DEFAULT_SLUG_PRIBADI, FinanceEntity::DEFAULT_SLUG_USAHA_KEBUN] as $slug) {
            $entity = FinanceEntity::query()->where('slug', $slug)->first();
            if (! $entity instanceof FinanceEntity) {
                continue;
            }

            $entityBalance = $balances->balanceForEntity($entity);
            $this->newLine();
            $this->line('Default entity: '.$entity->name.' ('.$slug.')');
            $this->line('  Legacy Global Balance: '.number_format($legacyBalance, 2, '.', ''));
            $this->line('  New Account-Based Balance: '.number_format($entityBalance, 2, '.', ''));
            $this->line('  Difference: '.number_format($legacyBalance - $entityBalance, 2, '.', ''));
        }

        $this->newLine();
        $this->info('Sources of difference');
        $this->line('- Legacy SaldoGlobalService is one unscoped number (all entities + saldos). New balance is per FinanceAccount / FinanceEntity.');
        $this->line('- Manual saldos (saldos.income_id IS NULL) are in legacy only: '.number_format($manualSaldos, 2, '.', ''));
        $this->line('- Opening balances are in the new formula only: '.number_format($openingAll, 2, '.', ''));
        $this->line('- Unmapped movements (finance_account_id null) stay out of account balances: '.$unmapped);
        $this->line('- Income → Saldo sync is not added again in the new formula (avoids double counting).');
        $this->line('- Opening balance was not seeded from legacy global (that would double-count migrated history).');
        $this->line('- Legacy breakdown: income='.number_format($legacyBreakdown['income'], 2, '.', '')
            .' cash_out='.number_format($legacyBreakdown['cash_out'], 2, '.', '')
            .' saldo='.number_format($legacyBreakdown['saldo'], 2, '.', ''));

        $this->newLine();
        $this->info('Cross-entity event reconciliation');
        $this->line('Capital uses one amount column. FAMILY out must equal BUSINESS in.');
        $this->table(
            ['Event', 'Source out', 'Destination in', 'Delta'],
            [
                $this->crossRow('Capital', $cross['capital_out'], $cross['capital_in']),
                $this->crossRow('Prive', $cross['withdrawal_out'], $cross['withdrawal_in']),
                $this->crossRow('Profit distribution', $cross['distribution_out'], $cross['distribution_in']),
            ]
        );
        $this->line('Recorded capital rows: '.number_format((float) BusinessCapitalContribution::query()->sum('amount'), 2, '.', ''));
        $this->line('Recorded prive rows: '.number_format((float) OwnerWithdrawal::query()->sum('amount'), 2, '.', ''));
        $this->line('Recorded distribution rows: '.number_format((float) ProfitDistribution::query()->sum('amount'), 2, '.', ''));
        $this->line('Formula path mismatch (summary vs breakdown): '.$formulaMismatch);

        $this->newLine();
        $this->info('No data was written.');

        $crossMismatch = abs($cross['capital_out'] - $cross['capital_in']) > 0.009
            || abs($cross['withdrawal_out'] - $cross['withdrawal_in']) > 0.009
            || abs($cross['distribution_out'] - $cross['distribution_in']) > 0.009;

        if ($formulaMismatch > 0 || $crossMismatch) {
            $this->error('Critical balance mismatch found.');

            return self::FAILURE;
        }

        $this->info('Account formula and cross-entity amounts are consistent.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function crossRow(string $label, float $out, float $in): array
    {
        return [
            $label,
            number_format($out, 2, '.', ''),
            number_format($in, 2, '.', ''),
            number_format($out - $in, 2, '.', ''),
        ];
    }

    private function manualSaldoTotal(): float
    {
        if (! Schema::hasTable('saldos')) {
            return 0.0;
        }

        if (Schema::hasColumn('saldos', 'income_id')) {
            return (float) Saldo::query()->whereNull('income_id')->sum('amount');
        }

        return (float) Saldo::query()->sum('amount');
    }
}
