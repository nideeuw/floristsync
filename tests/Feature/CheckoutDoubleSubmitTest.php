<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\TransactionService;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Tests\TestCase;

class CheckoutDoubleSubmitTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $this->member = User::factory()->create([
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'product_name' => 'Daisy Bouquet',
            'price' => 100000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test')->byDefault();
            $mock->shouldReceive('cancelInvoice')->andReturnNull()->byDefault();
            $mock->shouldReceive('getStatus')->andReturn(null)->byDefault();
        });
    }

    private function addToCart(int $qty = 1): void
    {
        $this->actingAs($this->member)
            ->post(route('cart.add', $this->product), ['qty' => $qty])
            ->assertRedirect();
    }

    private function checkout(): TestResponse
    {
        return $this->actingAs($this->member)->post(route('checkout.store'), [
            'name' => 'Member',
            'phone' => '081234567890',
            'fulfillment' => 'pickup',
        ]);
    }

    private function orderCount(): int
    {
        return Transaction::where('user_id', $this->member->id)->count();
    }

    private function placeOrders(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->addToCart(1);
            $this->checkout()->assertStatus(303);
        }
    }

    public function test_checkout_redirects_with_303_to_the_payment_page(): void
    {
        $this->addToCart(2);

        $response = $this->checkout();

        $transaction = Transaction::where('user_id', $this->member->id)->firstOrFail();

        $response->assertStatus(303)->assertRedirect(route('track.pay', $transaction->id));
    }

    public function test_two_quick_posts_create_one_order(): void
    {
        $this->addToCart(2);

        $first = $this->checkout();
        $second = $this->checkout();

        $first->assertStatus(303);
        $second->assertRedirect(route('cart'));

        $this->assertSame(1, $this->orderCount());
        $this->assertSame(8, $this->product->fresh()->stock);

        // The lock is released after the request.
        $lock = Cache::lock("checkout:{$this->member->id}", 10);
        $this->assertTrue($lock->get());
        $lock->release();
    }

    public function test_post_is_refused_while_another_checkout_holds_the_lock(): void
    {
        $this->addToCart(2);

        $lock = Cache::lock("checkout:{$this->member->id}", 10);
        $this->assertTrue($lock->get());

        $this->from(route('checkout'))
            ->actingAs($this->member)
            ->post(route('checkout.store'), [
                'name' => 'Member',
                'phone' => '081234567890',
                'fulfillment' => 'pickup',
            ])
            ->assertRedirect(route('checkout'))
            ->assertSessionHas('error');

        $this->assertSame(0, $this->orderCount());
        $this->assertSame(10, $this->product->fresh()->stock);

        $lock->release();

        $this->checkout()->assertStatus(303);
        $this->assertSame(1, $this->orderCount());
    }

    public function test_refresh_after_checkout_does_not_create_another_order(): void
    {
        $this->addToCart(2);

        $this->checkout()->assertStatus(303);

        $transaction = Transaction::where('user_id', $this->member->id)->firstOrFail();
        $url = route('track.pay', $transaction->id);

        $this->actingAs($this->member)->get($url)->assertOk();
        $this->actingAs($this->member)->get($url)->assertOk();

        $this->assertSame(1, $this->orderCount());
        $this->assertSame(8, $this->product->fresh()->stock);
    }

    public function test_max_pending_orders_defaults_to_three(): void
    {
        $this->assertSame(3, config('floristsync.max_pending_orders'));
    }

    public function test_fourth_pending_order_is_refused_and_stock_is_unchanged(): void
    {
        config(['floristsync.max_pending_orders' => 3]);

        $this->placeOrders(3);
        $this->assertSame(7, $this->product->fresh()->stock);

        $this->addToCart(1);

        $this->checkout()
            ->assertRedirect(route('track'))
            ->assertSessionHas('error', 'You have 3 unpaid orders. Please pay or cancel one first.');

        $this->assertSame(3, $this->orderCount());
        $this->assertSame(7, $this->product->fresh()->stock);
    }

    public function test_cancelling_an_unpaid_order_frees_a_slot(): void
    {
        config(['floristsync.max_pending_orders' => 3]);

        $this->placeOrders(3);

        $this->addToCart(1);
        $this->checkout()->assertRedirect(route('track'));

        $first = Transaction::where('user_id', $this->member->id)->oldest('id')->firstOrFail();
        app(TransactionService::class)->cancel($first);

        $this->checkout()->assertStatus(303);

        $this->assertSame(4, $this->orderCount());
        $this->assertSame(3, Transaction::where('user_id', $this->member->id)
            ->where('status', Transaction::PENDING_PAYMENT)->count());
    }

    public function test_checkout_page_disables_the_submit_button_after_the_first_click(): void
    {
        $this->addToCart(1);

        $this->actingAs($this->member)
            ->get(route('checkout', ['selected' => [$this->product->id]]))
            ->assertOk()
            ->assertSee('submitting = true', false)
            ->assertSee('!canSubmit || submitting', false);
    }
}
