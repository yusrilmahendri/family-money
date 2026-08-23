<?php

namespace App\Console\Commands;

use App\Services\RecurringTransactionRunner;
use Illuminate\Console\Command;

class FinanceRecurringRunCommand extends Command
{
    protected $signature = 'finance:recurring-run';

    protected $description = 'Post due entity recurring transactions';

    public function handle(RecurringTransactionRunner $runner): int
    {
        $created = $runner->runDue();
        $this->info('Posted '.$created.' recurring transaction(s).');

        return self::SUCCESS;
    }
}
