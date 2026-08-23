<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReceivableRequest extends FormRequest
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
        return [
            'party_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'principal_amount' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->parsedAmount('principal_amount') <= 0) {
                        $fail('Jumlah piutang harus lebih dari 0.');
                    }
                },
            ],
            'receivable_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:receivable_date'],
            'remaining_balance' => ['prohibited'],
            'status' => ['prohibited'],
            'finance_entity_id' => ['prohibited'],
            'public_id' => ['prohibited'],
        ];
    }

    /**
     * @return array{party_name: string, description: ?string, principal_amount: float, receivable_date: mixed, due_date: mixed}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'party_name' => $validated['party_name'],
            'description' => $validated['description'] ?? null,
            'principal_amount' => $this->parsedAmount('principal_amount'),
            'receivable_date' => $validated['receivable_date'],
            'due_date' => $validated['due_date'] ?? null,
        ];
    }

    public function parsedAmount(string $field): float
    {
        $digits = preg_replace('/\D/', '', (string) $this->input($field));

        return (float) ($digits === '' ? 0 : $digits);
    }
}
