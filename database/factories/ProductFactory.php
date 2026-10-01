<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $style = fake()->randomElement([
            'Blush', 'Sage', 'Meadow', 'Velvet', 'Pastel', 'Golden', 'Rustic', 'Classic', 'Dreamy', 'Sunset',
        ]);

        $flower = fake()->randomElement([
            'Rose', 'Tulip', 'Peony', 'Lily', 'Hydrangea', 'Gerbera', 'Daisy', 'Orchid', 'Sunflower', 'Carnation',
        ]);

        return [
            'category_id' => Category::factory(),
            'product_name' => "{$style} {$flower}",
            'description' => fake()->paragraph(2),
            'price' => fake()->numberBetween(8, 80) * 10000, // Rp 80.000 - Rp 800.000
            'image' => null,
            'stock' => fake()->numberBetween(0, 30),
            'status' => Product::STATUS_AVAILABLE,
        ];
    }
}
