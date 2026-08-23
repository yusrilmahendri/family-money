<?php

namespace Database\Factories;

use App\Enums\ReceivableStatus;
use App\Models\FinanceEntity;
use App\Models\Receivable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receivable>
 */
class ReceivableFactory extends Factory
{
    protected $model = Receivable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $principal = 10_000;

        return [
            'finance_entity_id' => FinanceEntity::factory()->family(),
            'party_name' => fake()->name(),
            'description' => fake()->optional()->sentence(),
            'principal_amount' => $principal,
            'remaining_balance' => $principal,
            'receivable_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => ReceivableStatus::OPEN,
        ];
    }
}
