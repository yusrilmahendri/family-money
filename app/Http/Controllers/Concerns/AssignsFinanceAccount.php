<?php

namespace App\Http\Controllers\Concerns;

use App\Models\FinanceEntity;
use App\Services\FinanceAccountService;
use App\Support\FinanceOwnership;
use Illuminate\Validation\Rule;

trait AssignsFinanceAccount
{
    /**
     * @return array<string, mixed>
     */
    protected function financeAccountRules(FinanceEntity $entity, ?int $currentId = null): array
    {
        return [
            'finance_account_id' => [
                'nullable',
                'integer',
                Rule::exists('finance_accounts', 'id')->where(function ($query) use ($entity, $currentId): void {
                    $query->where('finance_entity_id', $entity->id)
                        ->where(function ($query) use ($currentId): void {
                            $query->where('is_active', true);
                            if ($currentId) {
                                $query->orWhere('id', $currentId);
                            }
                        });
                }),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function legacyAccountRules(string $context): array
    {
        $entity = FinanceOwnership::defaultEntityForContext($context);

        if (! $entity instanceof FinanceEntity) {
            return ['finance_account_id' => ['nullable', 'integer']];
        }

        return $this->financeAccountRules($entity);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function resolvedAccountId(array $validated, FinanceEntity $entity): int
    {
        if (! empty($validated['finance_account_id'])) {
            return (int) $validated['finance_account_id'];
        }

        return app(FinanceAccountService::class)->ensureDefaultAccount($entity)->id;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Models\FinanceAccount>
     */
    protected function selectableAccounts(FinanceEntity $entity, ?int $currentId = null)
    {
        return $entity->accounts()
            ->where(function ($query) use ($currentId): void {
                $query->where('is_active', true);
                if ($currentId) {
                    $query->orWhere('id', $currentId);
                }
            })
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }
}
