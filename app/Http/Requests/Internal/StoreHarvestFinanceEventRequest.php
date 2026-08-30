<?php

namespace App\Http\Requests\Internal;

use App\Enums\HarvestFinanceEventType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHarvestFinanceEventRequest extends FormRequest
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
        $event = (string) $this->input('event');
        $needsSaleSnapshot = in_array($event, [
            HarvestFinanceEventType::HARVEST_SALE_POSTED->value,
            HarvestFinanceEventType::HARVEST_SALE_PAYMENT_RECEIVED->value,
        ], true);
        $needsPayment = in_array($event, [
            HarvestFinanceEventType::HARVEST_SALE_PAYMENT_RECEIVED->value,
            HarvestFinanceEventType::HARVEST_SALE_PAYMENT_REVERSED->value,
        ], true);

        return [
            'event' => ['required', 'string', Rule::in(HarvestFinanceEventType::values())],
            'plantation_entity_public_id' => ['required', 'string', 'max:64'],
            'finance_entity_public_id' => ['nullable', 'string', 'max:64'],
            'sale' => ['required', 'array'],
            'sale.public_id' => ['required', 'string', 'max:64'],
            'sale.buyer_name' => [Rule::requiredIf($needsSaleSnapshot), 'nullable', 'string', 'max:255'],
            'sale.sale_date' => [Rule::requiredIf($needsSaleSnapshot), 'nullable', 'date'],
            'sale.total_amount' => [Rule::requiredIf($needsSaleSnapshot), 'nullable', 'numeric'],
            'sale.invoice_number' => ['nullable', 'string', 'max:255'],
            'sale.description' => ['nullable', 'string', 'max:255'],
            'sale.status' => ['nullable', 'string', 'max:32'],
            'payment' => [Rule::requiredIf($needsPayment), 'nullable', 'array'],
            'payment.public_id' => [Rule::requiredIf($needsPayment), 'nullable', 'string', 'max:64'],
            'payment.amount' => [Rule::requiredIf($event === HarvestFinanceEventType::HARVEST_SALE_PAYMENT_RECEIVED->value), 'nullable', 'numeric'],
            'payment.payment_date' => [Rule::requiredIf($event === HarvestFinanceEventType::HARVEST_SALE_PAYMENT_RECEIVED->value), 'nullable', 'date'],
            'payment.payment_method' => ['nullable', 'string', 'max:32'],
            'payment.reference_number' => ['nullable', 'string', 'max:255'],
            'payment.notes' => ['nullable', 'string', 'max:255'],
            'payment.status' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->validated();
    }
}
