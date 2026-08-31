<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePortalAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('expires_at') === '') {
            $this->merge(['expires_at' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'grants' => ['required', 'array', 'min:1'],
            'grants.*' => ['required', 'string'],
            'token' => ['prohibited'],
            'token_hash' => ['prohibited'],
            'plain_token' => ['prohibited'],
            'public_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grants.required' => 'Pilih minimal satu layanan.',
            'grants.min' => 'Pilih minimal satu layanan.',
        ];
    }
}
