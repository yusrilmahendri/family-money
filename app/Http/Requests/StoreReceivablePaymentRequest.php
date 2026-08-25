<?php

namespace App\Http\Requests;

use App\Models\FinanceEntity;
use App\Support\Rupiah;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceivablePaymentRequest extends FormRequest
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

        return [
            'finance_account_id' => [
                'required',
                'integer',
                Rule::exists('finance_accounts', 'id')->where(function ($query) use ($entity): void {
                    $query->where('finance_entity_id', $entity->id)
                        ->where('is_active', true);
                }),
            ],
            'amount' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->parsedAmount() <= 0) {
                        $fail('Jumlah pembayaran harus lebih dari 0.');
                    }
                },
            ],
            'payment_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'finance_entity_id' => ['prohibited'],
            'receivable_id' => ['prohibited'],
            'public_id' => ['prohibited'],
        ];
    }

    /**
     * @return array{finance_account_id: int, amount: float, payment_date: mixed, description: ?string}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'finance_account_id' => (int) $validated['finance_account_id'],
            'amount' => $this->parsedAmount(),
            'payment_date' => $validated['payment_date'],
            'description' => $validated['description'] ?? null,
        ];
    }

    public function parsedAmount(): float
    {
        return Rupiah::toFloat($this->input('amount'));
    }
}
