<?php

namespace App\Http\Requests\Concerns;

use App\Enums\FinanceAccountType;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use Illuminate\Validation\Rule;

trait ValidatesFinanceAccount
{
    /**
     * @return array<string, mixed>
     */
    protected function accountRules(?FinanceAccount $account = null): array
    {
        /** @var FinanceEntity $entity */
        $entity = $this->route('financeEntity');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('finance_accounts', 'name')
                    ->where('finance_entity_id', $entity->id)
                    ->ignore($account),
            ],
            'type' => ['required', Rule::enum(FinanceAccountType::class)],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'is_default' => ['sometimes', 'boolean'],
            'finance_entity_id' => ['prohibited'],
            'public_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Nama account sudah dipakai di entity ini.',
            'finance_entity_id.prohibited' => 'finance_entity_id tidak boleh dikirim dari request.',
            'public_id.prohibited' => 'public_id tidak boleh dikirim dari request.',
        ];
    }

    protected function prepareAccountPayload(): void
    {
        $raw = $this->input('opening_balance');

        if (is_string($raw) && $raw !== '') {
            $digits = preg_replace('/\D/', '', $raw);
            $this->merge([
                'opening_balance' => $digits === '' ? 0 : $digits,
            ]);
        }

        if ($this->has('is_default')) {
            $this->merge([
                'is_default' => $this->boolean('is_default'),
            ]);
        }
    }
}
