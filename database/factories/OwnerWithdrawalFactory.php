<?php

namespace Database\Factories;

use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\OwnerWithdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OwnerWithdrawal>
 */
class OwnerWithdrawalFactory extends Factory
{
    protected $model = OwnerWithdrawal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_entity_id' => FinanceEntity::factory()->business(),
            'source_account_id' => function (array $attributes) {
                return FinanceAccount::factory()->cash()->create([
                    'finance_entity_id' => $attributes['business_entity_id'],
                    'name' => 'Kas Prive '.fake()->unique()->numerify('####'),
                ])->id;
            },
            'family_entity_id' => FinanceEntity::factory()->family(),
            'destination_account_id' => function (array $attributes) {
                return FinanceAccount::factory()->cash()->create([
                    'finance_entity_id' => $attributes['family_entity_id'],
                    'name' => 'Rekening Prive '.fake()->unique()->numerify('####'),
                ])->id;
            },
            'amount' => 10_000,
            'transaction_date' => now()->toDateString(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
