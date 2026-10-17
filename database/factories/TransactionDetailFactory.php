<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionDetail>
 */
class TransactionDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 3);
        $unitPrice = fake()->numberBetween(10, 50) * 10000;

        return [
            'transaction_id' => Transaction::factory(),
            'product_id' => Product::factory(),
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'subtotal' => $unitPrice * $qty,
        ];
    }
}
