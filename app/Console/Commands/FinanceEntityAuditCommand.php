<?php

namespace App\Console\Commands;

use App\Services\FinanceEntityOwnershipMigrator;
use Illuminate\Console\Command;

class FinanceEntityAuditCommand extends Command
{
    protected $signature = 'finance:entity-audit';

    protected $description = 'Read-only audit of finance entity ownership mapping';

    public function handle(FinanceEntityOwnershipMigrator $migrator): int
    {
        $audit = $migrator->audit();

        $this->info('Finance Entity Ownership Audit');
        $this->newLine();

        $labels = [
            'transactions' => 'Transactions',
            'categories' => 'Categories',
            'incomes' => 'Incomes',
            'budgets' => 'Budgets',
            'debts' => 'Debts',
            'savings_goals' => 'Savings Goals',
            'recurring_transactions' => 'Recurring Transactions',
        ];

        $rows = [];

        foreach ($labels as $table => $label) {
            $row = $audit[$table] ?? [
                'total' => 0,
                'mapped' => 0,
                'unmapped' => 0,
                'invalid' => 0,
                'mismatch' => 0,
                'unknown_context' => 0,
            ];

            $this->line($label.':');
            $this->line('  total: '.$row['total']);
            $this->line('  mapped: '.$row['mapped']);
            $this->line('  unmapped: '.$row['unmapped']);
            $this->line('  invalid entity references: '.$row['invalid']);
            $this->line('  legacy context mismatch: '.$row['mismatch']);
            $this->line('  unknown context: '.$row['unknown_context']);
            $this->newLine();

            $rows[] = [
                $label,
                $row['total'],
                $row['mapped'],
                $row['unmapped'],
                $row['invalid'],
                $row['mismatch'],
                $row['unknown_context'],
            ];
        }

        $this->table(
            ['Table', 'Total', 'Mapped', 'Unmapped', 'Invalid', 'Mismatch', 'Unknown context'],
            $rows
        );

        if ($migrator->hasCriticalInconsistencies()) {
            $this->error('Critical ownership inconsistency found.');

            return self::FAILURE;
        }

        $this->info('Ownership mapping is consistent.');

        return self::SUCCESS;
    }
}
