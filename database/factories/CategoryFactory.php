<?php

namespace Database\Factories;

use App\Models\FinanceEntity;
use App\Support\FinanceContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'context' => FinanceContext::PRIBADI,
            'finance_entity_id' => FinanceEntity::factory()->family(),
        ];
    }
}
