<?php

namespace App\Http\Requests;

use App\Models\FinanceEntity;
use App\Support\FinanceEntityAccess;
use App\Support\Rupiah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProfitDistributionRequest extends FormRequest
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
        /** @var FinanceEntity $business */
        $business = $this->route('financeEntity');

        return [
            'source_account_id' => [
                'required',
                'integer',
                Rule::exists('finance_accounts', 'id')->where(function ($query) use ($business): void {
                    $query->where('finance_entity_id', $business->id)
                        ->where('is_active', true);
                }),
            ],
            'family_public_id' => ['required', 'string', Rule::exists('finance_entities', 'public_id')],
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
            'distribution_date' => ['required', 'date'],
            'period_start' => ['nullable', 'date', 'required_with:period_end'],
            'period_end' => ['nullable', 'date', 'required_with:period_start', 'after_or_equal:period_start'],
            'description' => ['nullable', 'string', 'max:255'],
            'business_entity_id' => ['prohibited'],
            'family_entity_id' => ['prohibited'],
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

            $family = $this->resolvedFamily();

            if (! $family instanceof FinanceEntity || ! $family->isFamily() || ! $family->is_active) {
                $validator->errors()->add('family_public_id', 'Tujuan pembagian laba harus Family yang aktif.');

                return;
            }

            if (! $this->isAdminContext() && ! FinanceEntityAccess::isAuthorized($family)) {
                $validator->errors()->add('family_public_id', 'Anda tidak memiliki akses ke Family ini.');

                return;
            }

            $ownsDestination = $family->accounts()
                ->where('id', (int) $this->input('destination_account_id'))
                ->where('is_active', true)
                ->exists();

            if (! $ownsDestination) {
                $validator->errors()->add('destination_account_id', 'Account tujuan harus milik Family yang dipilih dan aktif.');
            }
        });
    }

    public function parsedAmount(): float
    {
        return Rupiah::toFloat($this->input('amount'));
    }

    public function resolvedFamily(): ?FinanceEntity
    {
        $publicId = (string) $this->input('family_public_id');

        if ($publicId === '') {
            return null;
        }

        return FinanceEntity::query()->where('public_id', $publicId)->first();
    }

    /**
     * @return array{
     *     source_account_id: int,
     *     destination_account_id: int,
     *     amount: float,
     *     distribution_date: mixed,
     *     period_start: mixed,
     *     period_end: mixed,
     *     description: ?string
     * }
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'source_account_id' => (int) $validated['source_account_id'],
            'destination_account_id' => (int) $validated['destination_account_id'],
            'amount' => $this->parsedAmount(),
            'distribution_date' => $validated['distribution_date'],
            'period_start' => $validated['period_start'] ?? null,
            'period_end' => $validated['period_end'] ?? null,
            'description' => $validated['description'] ?? null,
        ];
    }

    private function isAdminContext(): bool
    {
        return $this->routeIs('admin.*');
    }
}
