<?php

namespace App\Http\Controllers\Concerns;

use App\Services\AuditLogService;

trait RecordsAudit
{
    protected function auditLogs(): AuditLogService
    {
        return app(AuditLogService::class);
    }
}
