<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\CartService;
use App\Services\MidtransService;
use Database\Seeders\RolesMenusSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CartPersistenceTest extends TestCase
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
            'product_name' => 'Red Rose',
            'price' => 50000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->tulip = Product::factory()->create([
            'category_id' => $category->id,
            'product_name' => 'White Tulip',
            'price' => 75000,
            'stock' => 5,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);
    }

    public function test_guest_cart_stays_in_session_and_not_in_database(): void
    {
        $this->post(route('cart.add', $this->rose), ['qty' => 2])
            ->assertRedirect();

        $this->assertSame(2, session('cart')[$this->rose->id] ?? 0);
        $this->assertSame(0, CartItem::count());

        $cart = app(CartService::class);
        $this->assertSame(2, $cart->count());
        $this->assertCount(1, $cart->items());
    }

    public function test_logged_in_member_cart_is_stored_in_database_with_audit_columns(): void
    {
        $this->actingAs($this->member)
            ->post(route('cart.add', $this->rose), ['qty' => 3])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->member->id,
            'product_id' => $this->rose->id,
            'qty' => 3,
            'is_active' => true,
            'created_by' => $this->member->id,
        ]);

        $cartItem = CartItem::where('user_id', $this->member->id)->firstOrFail();
        $this->assertNotNull($cartItem->created_on);

        $cart = app(CartService::class);
        $this->assertSame(3, $cart->count());
        $this->assertSame([$this->rose->id => 3], $cart->raw());
    }

    public function test_cart_item_is_unique_per_user_and_product(): void
    {
        $this->actingAs($this->member)
            ->post(route('cart.add', $this->rose), ['qty' => 2])
            ->assertRedirect();

        $this->actingAs($this->member)
            ->post(route('cart.add', $this->rose), ['qty' => 3])
            ->assertRedirect();

        $this->assertSame(1, CartItem::where('user_id', $this->member->id)->where('product_id', $this->rose->id)->count());
        $this->assertSame(5, CartItem::where('user_id', $this->member->id)->where('product_id', $this->rose->id)->value('qty'));
    }

    public function test_logout_does_not_delete_database_cart(): void
    {
        $this->actingAs($this->member)
            ->post(route('cart.add', $this->rose), ['qty' => 3])
            ->assertRedirect();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->member->id,
            'product_id' => $this->rose->id,
            'qty' => 3,
        ]);

        $this->post(route('logout'))->assertRedirect();

        $this->assertGuest();
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->member->id,
            'product_id' => $this->rose->id,
            'qty' => 3,
        ]);

        // When member logs back in, items are still in cart
        $this->actingAs($this->member);
        $cart = app(CartService::class);
        $this->assertSame(3, $cart->count());
        $this->assertSame([$this->rose->id => 3], $cart->raw());
    }

    public function test_login_merges_guest_session_cart_into_member_database_cart_and_caps_at_stock(): void
    {
        // Member already has 3 roses in database
        CartItem::create([
            'user_id' => $this->member->id,
            'product_id' => $this->rose->id,
            'qty' => 3,
            'is_active' => true,
        ]);

        // Guest adds 4 roses (stock is 10) and 10 tulips (stock is 5) to session
        $this->withSession([
            'cart' => [
                $this->rose->id => 4,
                $this->tulip->id => 10,
            ],
        ]);

        // Login as member
        $response = $this->post('/login', [
            'email' => $this->member->email,
            'password' => 'password',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticated();

        // Session cart must be cleared
        $this->assertEmpty(session('cart', []));

        // Roses: 3 (existing) + 4 (guest) = 7 (below stock 10)
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->member->id,
            'product_id' => $this->rose->id,
            'qty' => 7,
        ]);

        // Tulips: 0 (existing) + 10 (guest) capped at tulip stock (5)
        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->member->id,
            'product_id' => $this->tulip->id,
            'qty' => 5,
        ]);
    }

    public function test_registration_merges_guest_session_cart_into_new_member_cart(): void
    {
        $this->withSession([
            'cart' => [
                $this->rose->id => 3,
            ],
        ]);

        $response = $this->post(route('register'), [
            'name' => 'New Customer',
            'email' => 'newcustomer@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticated();

        $newUser = User::where('email', 'newcustomer@example.com')->firstOrFail();

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $newUser->id,
            'product_id' => $this->rose->id,
            'qty' => 3,
        ]);

        $this->assertEmpty(session('cart', []));
    }

    public function test_inactive_and_unavailable_products_are_hidden_from_cart_and_badge(): void
    {
        $this->actingAs($this->member);

        $cart = app(CartService::class);
        $cart->add($this->rose, 2);
        $cart->add($this->tulip, 3);

        $this->assertSame(5, $cart->count());
        $this->assertCount(2, $cart->items());

        // Set rose to inactive
        $this->rose->update(['is_active' => false]);

        $this->assertSame(3, $cart->count());
        $items = $cart->items();
        $this->assertCount(1, $items);
        $this->assertSame($this->tulip->id, $items->first()->product->id);

        // Set tulip to unavailable status
        $this->tulip->update(['status' => Product::STATUS_UNAVAILABLE]);

        $this->assertSame(0, $cart->count());
        $this->assertCount(0, $cart->items());
    }

    public function test_stock_limits_are_enforced_when_adding_and_updating(): void
    {
        $this->actingAs($this->member);
        $cart = app(CartService::class);

        // Tulip stock is 5
        $cart->add($this->tulip, 5);
        $this->assertSame(5, $cart->count());

        // Adding more should throw DomainException
        try {
            $cart->add($this->tulip, 1);
            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('already have all available stock', $e->getMessage());
        }

        // Updating with value > stock should cap at stock
        $cart->update($this->tulip, 99);
        $this->assertSame(5, $cart->raw()[$this->tulip->id]);

        // Updating with 0 removes item
        $cart->update($this->tulip, 0);
        $this->assertArrayNotHasKey($this->tulip->id, $cart->raw());
    }

    public function test_checkout_clears_database_cart_after_order(): void
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test')->byDefault();
            $mock->shouldReceive('cancelInvoice')->andReturnNull()->byDefault();
            $mock->shouldReceive('getStatus')->andReturn(null)->byDefault();
        });

        $this->actingAs($this->member);
        $cart = app(CartService::class);
        $cart->add($this->rose, 2);

        $this->assertDatabaseHas('cart_items', [
            'user_id' => $this->member->id,
            'product_id' => $this->rose->id,
            'qty' => 2,
        ]);

        $response = $this->actingAs($this->member)->post(route('checkout.store'), [
            'name' => 'Member Customer',
            'phone' => '081234567890',
            'fulfillment' => 'pickup',
        ]);

        $response->assertOk();

        // Database cart must be cleared
        $this->assertDatabaseMissing('cart_items', [
            'user_id' => $this->member->id,
        ]);
        $this->assertSame(0, $cart->count());
        $this->assertTrue($cart->items()->isEmpty());
    }
}
