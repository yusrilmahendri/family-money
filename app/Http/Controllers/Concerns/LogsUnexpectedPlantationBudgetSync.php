<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\PlantationServiceException;
use App\Models\FinanceEntity;
use App\Models\PlantationOperatingBudget;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

trait LogsUnexpectedPlantationBudgetSync
{
    private function logUnexpectedPlantationBudgetSync(
        Throwable $exception,
        string $operation,
        FinanceEntity $financeEntity,
        ?PlantationOperatingBudget $budget = null,
    ): void {
        if ($exception instanceof PlantationServiceException || $exception instanceof InvalidArgumentException) {
            return;
        }

        $context = [
            'operation' => $operation,
            'controller' => static::class,
            'exception_class' => $exception::class,
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine(),
            'finance_entity_public_id' => $financeEntity->public_id,
            'budget_public_id' => $budget?->public_id,
        ];

        $routeName = request()->route()?->getName();
        if (is_string($routeName) && $routeName !== '') {
            $context['route_name'] = $routeName;
        }

        $sqlstate = $this->safeBudgetSyncSqlState($exception);
        if ($sqlstate !== null) {
            $context['sqlstate'] = $sqlstate;
        }

        Log::warning('plantation.budget_sync_unexpected_failed', $context);
    }

    private function safeBudgetSyncSqlState(Throwable $exception): ?string
    {
        if (! $exception instanceof QueryException) {
            return null;
        }

        $fromErrorInfo = $exception->errorInfo[0] ?? null;
        if (is_string($fromErrorInfo) && $fromErrorInfo !== '') {
            return $fromErrorInfo;
        }

        $code = trim((string) $exception->getCode());

        return $code !== '' && $code !== '0' ? $code : null;
    }
};
