<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;

class DummyDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Hand Bouquet'    => 'Bouquet',
            'Flower Box'      => 'Box',
            'Standing Flower' => 'Stand',
            'Single Stems'    => 'Stem',
            'Gift Hampers'    => 'Hamper',
        ];

        foreach ($categories as $name => $suffix) {
            $category = Category::create([
                'category_name' => $name,
                'description' => "Beautiful {$name} for every occasion.",
            ]);

            Product::factory()
                ->count(6)
                ->state(fn (array $attributes) => [
                    'category_id' => $category->id,
                    'product_name' => "{$attributes['product_name']} {$suffix}",
                ])
                ->create();
        }

        // Make a couple of products sold out, to test the "Sold out" UI.
        Product::inRandomOrder()->take(2)->get()->each->update(['stock' => 0]);
    }
}
