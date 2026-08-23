<?php

namespace App\Http\Requests;

use App\Models\FinanceEntity;
use App\Support\FinanceEntityAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBusinessCapitalContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var FinanceEntity $family */
        $family = $this->route('financeEntity');

        return [
            'source_account_id' => [
                'required',
                'integer',
                Rule::exists('finance_accounts', 'id')->where(function ($query) use ($family): void {
                    $query->where('finance_entity_id', $family->id)
                        ->where('is_active', true);
                }),
            ],
            'business_public_id' => ['required', 'string', Rule::exists('finance_entities', 'public_id')],
            'destination_account_id' => ['required', 'integer', 'exists:finance_accounts,id'],
            'amount' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->parsedAmount() <= 0) {
                        $fail('Jumlah harus lebih dari 0.');
                    }
                },
            ],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'source_entity_id' => ['prohibited'],
            'business_entity_id' => ['prohibited'],
            'finance_entity_id' => ['prohibited'],
            'public_id' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $business = $this->resolvedBusiness();

            if (! $business instanceof FinanceEntity || ! $business->isBusiness() || ! $business->is_active) {
                $validator->errors()->add('business_public_id', 'Tujuan modal harus usaha yang aktif.');

                return;
            }

            if (! $this->isAdminContext() && ! FinanceEntityAccess::isAuthorized($business)) {
                $validator->errors()->add('business_public_id', 'Anda tidak memiliki akses ke usaha ini.');

                return;
            }

            $ownsDestination = $business->accounts()
                ->where('id', (int) $this->input('destination_account_id'))
                ->where('is_active', true)
                ->exists();

            if (! $ownsDestination) {
                $validator->errors()->add('destination_account_id', 'Account tujuan harus milik usaha yang dipilih dan aktif.');
            }
        });
    }

    public function parsedAmount(): float
    {
        $digits = preg_replace('/\D/', '', (string) $this->input('amount'));

        return (float) ($digits === '' ? 0 : $digits);
    }

    public function resolvedBusiness(): ?FinanceEntity
    {
        $publicId = (string) $this->input('business_public_id');

        if ($publicId === '') {
            return null;
        }

        return FinanceEntity::query()->where('public_id', $publicId)->first();
    }

    /**
     * @return array{source_account_id: int, destination_account_id: int, amount: float, transaction_date: mixed, description: ?string}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'source_account_id' => (int) $validated['source_account_id'],
            'destination_account_id' => (int) $validated['destination_account_id'],
            'amount' => $this->parsedAmount(),
            'transaction_date' => $validated['transaction_date'],
            'description' => $validated['description'] ?? null,
        ];
    }

    private function isAdminContext(): bool
    {
        return $this->routeIs('admin.*');
    }
}
