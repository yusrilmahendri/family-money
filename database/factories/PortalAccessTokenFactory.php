<?php

namespace Database\Factories;

use App\Models\PortalAccessToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortalAccessToken>
 */
class PortalAccessTokenFactory extends Factory
{
    protected $model = PortalAccessToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'token_hash' => PortalAccessToken::hashToken(PortalAccessToken::generatePlainToken()),
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
