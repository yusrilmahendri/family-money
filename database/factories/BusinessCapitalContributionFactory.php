<?php

namespace Database\Factories;

use App\Models\BusinessCapitalContribution;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessCapitalContribution>
 */
class BusinessCapitalContributionFactory extends Factory
{
    protected $model = BusinessCapitalContribution::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_entity_id' => FinanceEntity::factory()->family(),
            'source_account_id' => function (array $attributes) {
                return FinanceAccount::factory()->cash()->create([
                    'finance_entity_id' => $attributes['source_entity_id'],
                    'name' => 'Kas Modal '.fake()->unique()->numerify('####'),
                ])->id;
            },
            'business_entity_id' => FinanceEntity::factory()->business(),
            'destination_account_id' => function (array $attributes) {
                return FinanceAccount::factory()->cash()->create([
                    'finance_entity_id' => $attributes['business_entity_id'],
                    'name' => 'Kas Usaha '.fake()->unique()->numerify('####'),
                ])->id;
            },
            'amount' => 10_000,
            'transaction_date' => now()->toDateString(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
