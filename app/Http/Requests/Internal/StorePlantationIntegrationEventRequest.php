<?php

namespace App\Http\Requests\Internal;

use App\Enums\IntegrationEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlantationIntegrationEventRequest extends FormRequest
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
            'event_id' => ['required', 'string', 'max:64'],
            'event_type' => ['required', 'string', Rule::in(IntegrationEventType::values())],
            'event_version' => ['required', 'integer', 'min:1'],
            'occurred_at' => ['required', 'string', 'max:64'],
            'plantation_entity_public_id' => ['required', 'string', 'max:64'],
            'finance_entity_public_id' => ['required', 'string', 'max:64'],
            'source_public_id' => ['required', 'string', 'max:64'],
            'payload' => ['required', 'array'],
        ];
    }
}
