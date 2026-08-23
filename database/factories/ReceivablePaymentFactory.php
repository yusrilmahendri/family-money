<?php

namespace Database\Factories;

use App\Models\FinanceAccount;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceivablePayment>
 */
class ReceivablePaymentFactory extends Factory
{
    protected $model = ReceivablePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receivable_id' => Receivable::factory(),
            'finance_account_id' => function (array $attributes) {
                $receivable = Receivable::query()->find($attributes['receivable_id']);

                return FinanceAccount::factory()->cash()->create([
                    'finance_entity_id' => $receivable?->finance_entity_id,
                    'name' => 'Kas Piutang '.fake()->unique()->numerify('####'),
                ])->id;
            },
            'amount' => 4_000,
            'payment_date' => now()->toDateString(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
