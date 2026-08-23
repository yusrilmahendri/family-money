<?php

namespace Database\Factories;

use App\Enums\FinanceAccountType;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinanceAccount>
 */
class FinanceAccountFactory extends Factory
{
    protected $model = FinanceAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'finance_entity_id' => FinanceEntity::factory(),
            'name' => fake()->unique()->words(3, true),
            'type' => fake()->randomElement(FinanceAccountType::cases()),
            'bank_name' => fake()->optional()->company(),
            'account_number' => fake()->optional()->numerify('##########'),
            'description' => fake()->optional()->sentence(),
            'opening_balance' => 0,
            'is_active' => true,
            'is_default' => false,
        ];
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => FinanceAccountType::CASH,
            'bank_name' => null,
            'account_number' => null,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'is_default' => false,
        ]);
    }
}
