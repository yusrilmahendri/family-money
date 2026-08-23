<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\FinanceEntity;
use App\Support\FinanceContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'finance_entity_id' => FinanceEntity::factory()->family(),
            'category_id' => Category::factory(),
            'context' => FinanceContext::PRIBADI,
            'amount' => $this->faker->randomFloat(2, 50000, 1000000),
            'transaction_date' => $this->faker->date(),
            'description' => $this->faker->sentence(),
        ];
    }
}
