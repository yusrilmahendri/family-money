<?php

namespace App\Http\Requests\Admin;

use App\Support\Rupiah;
use Illuminate\Foundation\Http\FormRequest;

class StorePlantationOperatingBudgetRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'allocated_amount' => Rupiah::positiveRules(),
            'public_id' => ['prohibited'],
            'finance_entity_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }

    /**
     * @return array{name: string, period_start: string, period_end: string, allocated_amount: float}
     */
    public function payload(): array
    {
        return [
            'name' => (string) $this->input('name'),
            'period_start' => (string) $this->input('period_start'),
            'period_end' => (string) $this->input('period_end'),
            'allocated_amount' => Rupiah::toFloat($this->input('allocated_amount')),
        ];
    }
}
