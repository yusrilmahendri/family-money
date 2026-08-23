<?php

namespace Database\Factories;

use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\FinanceTransfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinanceTransfer>
 */
class FinanceTransferFactory extends Factory
{
    protected $model = FinanceTransfer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'finance_entity_id' => FinanceEntity::factory()->family(),
            'source_account_id' => function (array $attributes) {
                return FinanceAccount::factory()->cash()->create([
                    'finance_entity_id' => $attributes['finance_entity_id'],
                    'name' => 'Sumber '.fake()->unique()->numerify('####'),
                ])->id;
            },
            'destination_account_id' => function (array $attributes) {
                return FinanceAccount::factory()->cash()->create([
                    'finance_entity_id' => $attributes['finance_entity_id'],
                    'name' => 'Tujuan '.fake()->unique()->numerify('####'),
                ])->id;
            },
            'amount' => 10_000,
            'transaction_date' => now()->toDateString(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
