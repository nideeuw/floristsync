<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'midtrans_order_id' => 'FS-'.fake()->unique()->numberBetween(1, 999999).'-'.strtoupper(Str::random(6)),
            'payment_method' => 'gopay',
            'payment_status' => 'pending',
            'amount' => fake()->numberBetween(50, 500) * 1000,
            'payment_date' => null,
        ];
    }

    public function settlement(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'settlement',
            'payment_date' => now(),
        ]);
    }
}
