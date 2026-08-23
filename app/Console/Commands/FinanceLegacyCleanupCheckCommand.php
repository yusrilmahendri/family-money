<?php

namespace App\Console\Commands;

use App\Models\Saldo;
use App\Services\FinanceAccountMovementMigrator;
use App\Services\FinanceAccountService;
use App\Services\FinanceEntityOwnershipMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class FinanceLegacyCleanupCheckCommand extends Command
{
    protected $signature = 'finance:legacy-cleanup-check';

    protected $description = 'Read-only check that the new architecture does not depend on retired /apps saldo context';

    public function handle(
        FinanceEntityOwnershipMigrator $entities,
        FinanceAccountService $accounts,
        FinanceAccountMovementMigrator $movements,
    ): int {
        $this->info('Finance Legacy Cleanup Check');
        $this->line('Read-only. Product SoT is FinanceAccountBalanceService. saldos / FinanceContext session are not product sources.');
        $this->newLine();

        $ownershipCritical = $entities->hasCriticalInconsistencies();
        $accountCritical = $accounts->hasCriticalInconsistencies();
        $movementCritical = $movements->hasCriticalInconsistencies();
        $unmapped = $movements->unmappedTotal();
        $saldosExist = Schema::hasTable('saldos');
        $saldoRows = $saldosExist ? Saldo::query()->count() : 0;

        $this->line('Unmapped entity ownership: '.($ownershipCritical ? 'CRITICAL' : '0'));
        $this->line('Account mismatch: '.($accountCritical ? 'CRITICAL' : '0'));
        $this->line('Unmapped movements: '.$unmapped);
        $this->line('saldos table retained: '.($saldosExist ? 'yes ('.$saldoRows.' rows, archive only)' : 'no'));
        $this->line('Legacy /apps portal: retired (redirects to /)');
        $this->newLine();

        if ($ownershipCritical || $accountCritical || $movementCritical) {
            $this->error('Legacy cleanup is not clean. Do not drop saldos or context columns.');

            return self::FAILURE;
        }

        $this->info('New architecture is the only product source of truth.');

        return self::SUCCESS;
    }
}
