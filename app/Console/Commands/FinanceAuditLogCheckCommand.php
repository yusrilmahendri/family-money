<?php

namespace App\Console\Commands;

use App\Services\AuditLogService;
use Illuminate\Console\Command;

class FinanceAuditLogCheckCommand extends Command
{
    protected $signature = 'finance:audit-log-check';

    protected $description = 'Read-only integrity check of audit_logs';

    public function handle(AuditLogService $audit): int
    {
        $check = $audit->integrityCheck();

        $this->info('Audit Log Check');
        $this->newLine();
        $this->line('Logs without a valid action: '.$check['missing_action']);
        $this->line('Invalid actor type: '.$check['invalid_actor_type']);
        $this->line('Sensitive fields stored: '.$check['sensitive_fields']);
        $this->line('Malformed JSON: '.$check['malformed_json']);
        $this->line('Invalid entity reference: '.$check['invalid_entity_reference']);
        $this->newLine();
        $this->table(
            ['Check', 'Count'],
            [
                ['Missing / invalid action', $check['missing_action']],
                ['Invalid actor type', $check['invalid_actor_type']],
                ['Sensitive fields', $check['sensitive_fields']],
                ['Malformed JSON', $check['malformed_json']],
                ['Invalid entity reference', $check['invalid_entity_reference']],
            ]
        );

        if ($audit->hasIntegrityIssues()) {
            $this->error('Audit log integrity issues found.');

            return self::FAILURE;
        }

        $this->info('Audit logs look consistent.');

        return self::SUCCESS;
    }
}
