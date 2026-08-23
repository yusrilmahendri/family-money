<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesFinanceAccount;
use Illuminate\Foundation\Http\FormRequest;

class StoreFinanceAccountRequest extends FormRequest
{
    use ValidatesFinanceAccount;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareAccountPayload();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->accountRules();
    }
}
