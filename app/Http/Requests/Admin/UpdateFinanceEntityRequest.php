<?php

namespace App\Http\Requests\Admin;

use App\Enums\FinanceEntityType;
use App\Models\FinanceEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateFinanceEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $slug = $this->input('slug');

        $this->merge([
            'slug' => filled($slug) ? Str::slug((string) $slug) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var FinanceEntity|null $entity */
        $entity = $this->route('financeEntity');

        $typeRules = ['required', Rule::enum(FinanceEntityType::class)];
        if ($entity instanceof FinanceEntity && $entity->hasFinancialRecords()) {
            $typeRules[] = Rule::in([$entity->type->value]);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => $typeRules,
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('finance_entities', 'slug')->ignore($entity),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => 'Tipe FinanceEntity tidak dapat diubah karena sudah memiliki data keuangan.',
        ];
    }
}
