<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\PlantationServiceException;
use App\Models\FinanceEntity;
use App\Models\PlantationOperatingBudget;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

trait LogsUnexpectedPlantationBudgetSync
{
    /**
     * @var list<string>
     */
    private const BUDGET_SYNC_SENSITIVE_MESSAGE_MARKERS = [
        'service token',
        'service_token',
        'authorization',
        'private access token',
        'private_access_token',
        'access token',
        'access_token',
        'password',
        'secret',
        'bearer',
        'token',
    ];

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
            'finance_entity_public_id' => $financeEntity->public_id,
            'budget_public_id' => $budget?->public_id,
        ];

        $routeName = request()->route()?->getName();
        if (is_string($routeName) && $routeName !== '') {
            $context['route_name'] = $routeName;
        }

        $safeMessage = $this->safeBudgetSyncExceptionMessage($exception->getMessage());
        if ($safeMessage !== null) {
            $context['exception_message'] = $safeMessage;
        } else {
            $context['file'] = basename($exception->getFile());
            $context['line'] = $exception->getLine();
        }

        Log::warning('plantation.budget_sync_unexpected_failed', $context);
    }

    private function safeBudgetSyncExceptionMessage(string $message): ?string
    {
        $normalized = strtolower($message);

        foreach (self::BUDGET_SYNC_SENSITIVE_MESSAGE_MARKERS as $marker) {
            if (str_contains($normalized, $marker)) {
                return null;
            }
        }

        $trimmed = trim($message);

        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, 300);
    }
}
