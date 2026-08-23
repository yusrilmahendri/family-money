<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesFinanceAccount;
use App\Models\FinanceAccount;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFinanceAccountRequest extends FormRequest
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
        /** @var FinanceAccount|null $account */
        $account = $this->route('account');

        return $this->accountRules($account instanceof FinanceAccount ? $account : null);
    }
}
