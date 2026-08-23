<?php

namespace Database\Factories;

use App\Models\FinanceEntity;
use App\Models\FinanceEntityAccessToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FinanceEntityAccessToken>
 */
class FinanceEntityAccessTokenFactory extends Factory
{
    protected $model = FinanceEntityAccessToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'finance_entity_id' => FinanceEntity::factory(),
            'token_hash' => FinanceEntityAccessToken::hashToken(FinanceEntityAccessToken::generatePlainToken()),
            'label' => fake()->optional()->words(2, true),
            'is_active' => true,
            'expires_at' => null,
            'last_used_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subHour(),
        ]);
    }
}
