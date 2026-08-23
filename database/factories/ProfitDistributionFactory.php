<?php

namespace Database\Factories;

use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\ProfitDistribution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfitDistribution>
 */
class ProfitDistributionFactory extends Factory
{
    protected $model = ProfitDistribution::class;

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
                    'name' => 'Kas Laba '.fake()->unique()->numerify('####'),
                ])->id;
            },
            'family_entity_id' => FinanceEntity::factory()->family(),
            'destination_account_id' => function (array $attributes) {
                return FinanceAccount::factory()->cash()->create([
                    'finance_entity_id' => $attributes['family_entity_id'],
                    'name' => 'Rekening Laba '.fake()->unique()->numerify('####'),
                ])->id;
            },
            'amount' => 10_000,
            'distribution_date' => now()->toDateString(),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
