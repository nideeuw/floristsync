<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CartService;
use App\Services\MidtransService;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CartSelectCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Product $rose;

    private Product $tulip;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $this->member = User::factory()->create([
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'email' => 'member@example.com',
            'is_active' => true,
        ]);

        $category = Category::factory()->create();

        $this->rose = Product::factory()->create([
            'category_id' => $category->id,
            'product_name' => 'Red Rose Bouquet',
            'price' => 50000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->tulip = Product::factory()->create([
            'category_id' => $category->id,
            'product_name' => 'White Tulip Bouquet',
            'price' => 75000,
            'stock' => 8,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test')->byDefault();
            $mock->shouldReceive('cancelInvoice')->andReturnNull()->byDefault();
            $mock->shouldReceive('getStatus')->andReturn(null)->byDefault();
        });
    }

    public function test_cart_page_shows_checkboxes_and_select_all(): void
    {
        $this->actingAs($this->member);

        $cart = app(CartService::class);
        $cart->add($this->rose, 2);
        $cart->add($this->tulip, 1);

        $response = $this->get(route('cart'));

        $response->assertOk();
        $response->assertSee('Select all');
        $response->assertSee('name="selected[]"', false);
        $response->assertSee('Red Rose Bouquet');
        $response->assertSee('White Tulip Bouquet');
        $response->assertSee('Proceed to checkout');
    }

    public function test_checkout_page_displays_only_selected_items_and_recomputed_subtotal(): void
    {
        $this->actingAs($this->member);

        $cart = app(CartService::class);
        $cart->add($this->rose, 2); // 2 * 50,000 = 100,000
        $cart->add($this->tulip, 1); // 1 * 75,000 = 75,000

        // Only select rose
        $response = $this->get(route('checkout', ['selected' => [$this->rose->id]]));

        $response->assertOk();
        $response->assertSee('Red Rose Bouquet');
        $response->assertDontSee('White Tulip Bouquet');
        $response->assertSee(rupiah(100000));
        $response->assertDontSee(rupiah(175000));
    }

    public function test_checkout_redirects_to_cart_when_nothing_selected(): void
    {
        $this->actingAs($this->member);

        $cart = app(CartService::class);
        $cart->add($this->rose, 2);

        // No selected parameter
        $response = $this->get(route('checkout'));
        $response->assertRedirect(route('cart'));
        $response->assertSessionHas('error', 'Please select at least one item to check out.');

        // Empty selected array
        $emptyResponse = $this->get(route('checkout', ['selected' => []]));
        $emptyResponse->assertRedirect(route('cart'));
        $emptyResponse->assertSessionHas('error', 'Please select at least one item to check out.');
    }

    public function test_checkout_redirects_to_cart_when_selected_item_not_in_cart(): void
    {
        $this->actingAs($this->member);

        $cart = app(CartService::class);
        $cart->add($this->rose, 1);

        // Tulip is not in member's cart
        $response = $this->get(route('checkout', ['selected' => [$this->tulip->id]]));

        $response->assertRedirect(route('cart'));
        $response->assertSessionHas('error', 'One or more selected items are not in your cart.');
    }

    public function test_checkout_store_rejects_when_selected_item_not_in_cart(): void
    {
        $this->actingAs($this->member);

        $cart = app(CartService::class);
        $cart->add($this->rose, 1);

        $response = $this->post(route('checkout.store'), [
            'name' => 'Member Customer',
            'phone' => '081234567890',
            'fulfillment' => 'pickup',
            'selected' => [$this->tulip->id],
        ]);

        $response->assertRedirect(route('cart'));
        $response->assertSessionHas('error', 'One or more selected items are not in your cart.');
    }

    public function test_partial_checkout_removes_only_ordered_items_unselected_items_stay(): void
    {
        $this->actingAs($this->member);

        $cart = app(CartService::class);
        $cart->add($this->rose, 2);
        $cart->add($this->tulip, 3);

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->member->id,
            'product_id' => $this->rose->id,
            'qty' => 2,
        ]);
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->member->id,
            'product_id' => $this->tulip->id,
            'qty' => 3,
        ]);

        // Place order selecting ONLY the rose
        $response = $this->post(route('checkout.store'), [
            'name' => 'Member Customer',
            'phone' => '081234567890',
            'fulfillment' => 'pickup',
            'selected' => [$this->rose->id],
        ]);

        $response->assertOk();

        // Transaction must contain ONLY 1 detail row for Rose
        $transaction = Transaction::where('user_id', $this->member->id)->latest('id')->firstOrFail();
        $this->assertCount(1, $transaction->details);
        $this->assertSame($this->rose->id, $transaction->details->first()->product_id);
        $this->assertSame(2, $transaction->details->first()->qty);
        $this->assertSame(100000, (int) $transaction->total);

        // Rose must be removed from cart
        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $this->member->id,
            'product_id' => $this->rose->id,
        ]);

        // Tulip MUST REMAIN in member's cart!
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->member->id,
            'product_id' => $this->tulip->id,
            'qty' => 3,
        ]);

        // Cart count should now be 3 (tulip only)
        $this->assertSame(3, $cart->count());
        $this->assertSame([$this->tulip->id => 3], $cart->raw());
    }
}
