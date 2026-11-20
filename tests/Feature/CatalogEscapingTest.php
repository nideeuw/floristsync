<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithSeededRoles;
use Tests\TestCase;

class CatalogEscapingTest extends TestCase
{
    use InteractsWithSeededRoles, RefreshDatabase;

    public function test_product_name_with_a_script_tag_is_shown_escaped_in_the_catalog(): void
    {
        $this->seedRolesAndMenus();

        $name = '<script>alert("xss")</script>';

        Product::factory()->create([
            'category_id' => Category::factory()->create(['is_active' => true])->id,
            'product_name' => $name,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
            'stock' => 10,
        ]);

        $response = $this->get(route('catalog'));

        $response->assertOk();
        $response->assertSee(e($name), false);
        $response->assertDontSee($name, false);
    }
}
