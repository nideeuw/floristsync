<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductImageService;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageStorageTest extends TestCase
{
    use RefreshDatabase;

    private string $diskName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->diskName = config('filesystems.product_image_disk');
        Storage::fake($this->diskName);

        $this->seed(RolesMenusSeeder::class);
    }

    private function inventoryAdmin(): User
    {
        return User::whereHas('role', fn ($q) => $q->where('role_name', 'admin_inventory'))->firstOrFail();
    }

    private function makeProduct(?string $image = null): Product
    {
        $category = Category::create(['category_name' => 'Test Category']);

        return Product::create([
            'category_id' => $category->id,
            'product_name' => 'Test Bouquet',
            'price' => 100000,
            'status' => Product::STATUS_AVAILABLE,
            'image' => $image,
        ]);
    }

    private function payload(Product $product, array $extra = []): array
    {
        return array_merge([
            'category_id' => $product->category_id,
            'product_name' => $product->product_name,
            'price' => 100000,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => 1,
        ], $extra);
    }

    public function test_upload_is_stored_on_the_object_storage_disk(): void
    {
        $product = $this->makeProduct();

        $this->actingAs($this->inventoryAdmin())
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'image' => UploadedFile::fake()->image('rose.jpg', 600, 600),
            ]))
            ->assertSessionHasNoErrors();

        $path = $product->fresh()->image;

        $this->assertStringStartsWith(ProductImageService::PREFIX.'/', $path);
        Storage::disk($this->diskName)->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_replacing_the_image_deletes_the_old_file(): void
    {
        $old = ProductImageService::PREFIX.'/old.jpg';
        Storage::disk($this->diskName)->put($old, 'old');
        $product = $this->makeProduct($old);

        $this->actingAs($this->inventoryAdmin())
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'image' => UploadedFile::fake()->image('new.png', 600, 600),
            ]))
            ->assertSessionHasNoErrors();

        $new = $product->fresh()->image;

        $this->assertNotSame($old, $new);
        Storage::disk($this->diskName)->assertMissing($old);
        Storage::disk($this->diskName)->assertExists($new);
    }

    public function test_image_rules_are_enforced(): void
    {
        $product = $this->makeProduct();
        $admin = $this->inventoryAdmin();

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'image' => UploadedFile::fake()->image('big.jpg', 600, 600)->size(2049),
            ]))
            ->assertSessionHasErrors('image');

        $this->actingAs($admin)
            ->put(route('admin.products.update', $product), $this->payload($product, [
                'image' => UploadedFile::fake()->create('photo.gif', 10, 'image/gif'),
            ]))
            ->assertSessionHasErrors('image');

        $this->assertNull($product->fresh()->image);
    }

    public function test_legacy_path_renders_the_placeholder(): void
    {
        $product = $this->makeProduct('products/legacy.jpg');

        $html = (string) $this->blade('<x-product-image :product="$product" />', ['product' => $product]);

        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_stored_path_renders_the_disk_url(): void
    {
        $path = ProductImageService::PREFIX.'/fresh.jpg';
        Storage::disk($this->diskName)->put($path, 'x');
        $product = $this->makeProduct($path);

        $html = (string) $this->blade('<x-product-image :product="$product" />', ['product' => $product]);

        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString($path, $html);
    }
}
