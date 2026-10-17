<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->numberBetween(50, 500) * 1000;

        return [
            'user_id' => User::factory(),
            'transaction_type' => Transaction::TYPE_ONLINE,
            'status' => Transaction::PENDING_PAYMENT,
            'total' => $total,
        ];
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Transaction::PROCESSING,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Transaction::CANCELLED,
        ]);
    }
}
