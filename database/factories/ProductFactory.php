<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use WeakMap;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /** @var WeakMap<Product, int>|null Stock requested through ->create(['stock' => N]). */
    private static ?WeakMap $requestedStock = null;

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
            'status' => Product::STATUS_AVAILABLE,
        ];
    }

    /**
     * Stock lives in the stocks table, so every new product gets an initial stock row.
     * Tests may still pass an exact amount: Product::factory()->create(['stock' => 10]).
     * `stock` is not a column anymore, so it is removed before the insert and
     * turned into the initial stock row instead.
     */
    public function configure(): static
    {
        return $this
            ->afterMaking(function (Product $product) {
                $attributes = $product->getAttributes();

                if (array_key_exists('stock', $attributes)) {
                    self::$requestedStock ??= new WeakMap;
                    self::$requestedStock[$product] = (int) $attributes['stock'];
                    unset($product->stock);
                }
            })
            ->afterCreating(function (Product $product) {
                $quantity = self::$requestedStock[$product] ?? fake()->numberBetween(0, 30);

                $product->stocks()->create([
                    'quantity_change' => $quantity,
                    'change_type' => 'in',
                    'notes' => 'Initial stock (factory)',
                    'date' => now(),
                ]);
            });
    }

    /** Test helper: make the product's current stock exactly $quantity. */
    public static function setStock(Product $product, int $quantity): void
    {
        DB::table('stocks')->where('product_id', $product->getKey())->delete();

        $product->stocks()->create([
            'quantity_change' => $quantity,
            'change_type' => 'in',
            'notes' => 'Stock set by test',
            'date' => now(),
        ]);
    }
}