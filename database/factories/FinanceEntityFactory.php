<?php

namespace Database\Factories;

use App\Enums\FinanceEntityType;
use App\Models\FinanceEntity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FinanceEntity>
 */
class FinanceEntityFactory extends Factory
{
    protected $model = FinanceEntity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'type' => fake()->randomElement(FinanceEntityType::cases()),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function family(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => FinanceEntityType::FAMILY,
        ]);
    }

    public function business(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => FinanceEntityType::BUSINESS,
        ]);
    }
}
