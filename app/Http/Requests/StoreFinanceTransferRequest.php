<?php

namespace App\Http\Requests;

use App\Models\FinanceEntity;
use App\Support\Rupiah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinanceTransferRequest extends FormRequest
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
        /** @var FinanceEntity $entity */
        $entity = $this->route('financeEntity');

        $activeOwned = Rule::exists('finance_accounts', 'id')->where(function ($query) use ($entity): void {
            $query->where('finance_entity_id', $entity->id)
                ->where('is_active', true);
        });

        return [
            'source_account_id' => ['required', 'integer', $activeOwned],
            'destination_account_id' => ['required', 'integer', 'different:source_account_id', $activeOwned],
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
            'destination_account_id.different' => 'Account tujuan harus berbeda dari account sumber.',
        ];
    }

    public function parsedAmount(): float
    {
        return Rupiah::toFloat($this->input('amount'));
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
}
