<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InteractsWithSeededRoles;
use Tests\TestCase;

class ProductImageUploadTest extends TestCase
{
    use InteractsWithSeededRoles, RefreshDatabase;

    private string $diskName;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndMenus();
        $this->diskName = config('filesystems.product_image_disk');
        Storage::fake($this->diskName);
    }

    public function test_php_file_renamed_to_jpg_is_rejected(): void
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('shell_', true).'.jpg';
        file_put_contents($path, "<?php echo 'pwned'; ?>");

        // The browser would report image/jpeg because of the .jpg name.
        $file = new UploadedFile($path, 'shell.jpg', 'image/jpeg', null, true);

        $response = $this->actingAs($this->userForRole(Role::ADMIN_INVENTORY))
            ->post(route('admin.products.store'), $this->payload(['image' => $file]));

        @unlink($path);

        $response->assertSessionHasErrors('image');
        $this->assertSame(0, Product::count());
        $this->assertSame([], Storage::disk($this->diskName)->allFiles());
    }

    public function test_gif_is_rejected(): void
    {
        $response = $this->actingAs($this->userForRole(Role::ADMIN_INVENTORY))
            ->post(route('admin.products.store'), $this->payload([
                'image' => UploadedFile::fake()->image('animated.gif', 100, 100),
            ]));

        $response->assertSessionHasErrors('image');
        $this->assertSame(0, Product::count());
    }

    public function test_image_wider_than_the_maximum_is_rejected(): void
    {
        $response = $this->actingAs($this->userForRole(Role::ADMIN_INVENTORY))
            ->post(route('admin.products.store'), $this->payload([
                'image' => UploadedFile::fake()->image('wide.png', 4001, 10),
            ]));

        $response->assertSessionHasErrors('image');
        $this->assertSame(0, Product::count());
    }

    public function test_valid_png_is_accepted_and_stored(): void
    {
        $response = $this->actingAs($this->userForRole(Role::ADMIN_INVENTORY))
            ->post(route('admin.products.store'), $this->payload([
                'image' => UploadedFile::fake()->image('peony.png', 800, 600),
            ]));

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('admin.products.index'));

        $product = Product::firstOrFail();
        Storage::disk($this->diskName)->assertExists($product->image);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => Category::factory()->create()->id,
            'product_name' => 'Pink Peony Bouquet',
            'price' => 150000,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => 1,
        ], $overrides);
    }
}
