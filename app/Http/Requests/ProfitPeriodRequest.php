<?php

namespace App\Http\Requests;

use App\Services\BusinessProfitService;
use Illuminate\Foundation\Http\FormRequest;

class ProfitPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('period') === 'month') {
            [$from, $to] = app(BusinessProfitService::class)->currentMonthBounds();
            $this->merge([
                'from' => $from,
                'to' => $to,
            ]);
        }

        if ($this->input('period') === 'all') {
            $this->merge([
                'from' => null,
                'to' => null,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period' => ['nullable', 'in:month,all'],
            'from' => ['nullable', 'date', 'required_with:to'],
            'to' => ['nullable', 'date', 'required_with:from', 'after_or_equal:from'],
        ];
    }

    /**
     * Inclusive Y-m-d bounds. Null means all-time.
     *
     * @return array{0: ?string, 1: ?string}
     */
    public function range(): array
    {
        if (! $this->filled('from') && ! $this->filled('to')) {
            return [null, null];
        }

        return [
            $this->date('from')?->toDateString(),
            $this->date('to')?->toDateString(),
        ];
    }
}
