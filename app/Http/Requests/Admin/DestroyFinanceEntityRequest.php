<?php

namespace App\Http\Requests\Admin;

use App\Models\FinanceEntity;
use Illuminate\Foundation\Http\FormRequest;

class DestroyFinanceEntityRequest extends FormRequest
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
            'confirmation' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $entity = $this->route('financeEntity');

            if (! $entity instanceof FinanceEntity) {
                $validator->errors()->add('confirmation', 'Finance entity tidak ditemukan.');

                return;
            }

            if (! $this->matchesConfirmation($entity, (string) $this->input('confirmation'))) {
                $validator->errors()->add(
                    'confirmation',
                    'Ketik nama entity atau HAPUS untuk menghapus permanen.'
                );
            }
        });
    }

    public function matchesConfirmation(FinanceEntity $entity, string $confirmation): bool
    {
        $value = trim($confirmation);

        if ($value === '') {
            return false;
        }

        return hash_equals($entity->name, $value)
            || strcasecmp($value, 'HAPUS') === 0;
    }
}
