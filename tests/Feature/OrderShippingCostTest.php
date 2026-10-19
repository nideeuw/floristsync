<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Transaction;
use App\Services\MidtransService;
use App\Services\ShippingService;
use App\Services\TransactionService;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

class OrderShippingCostTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $this->member = User::factory()->create([
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'role_id' => Role::where('role_name', Role::ADMIN_ORDER)->value('id'),
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'product_name' => 'Rose Bundle',
            'price' => 100000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);
    }

    private function createOrder(array $delivery, int $shippingCost = 15000): Transaction
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test');
            $mock->shouldReceive('cancelInvoice')->andReturnNull();
        });

        $this->mock(ShippingService::class, function (MockInterface $mock) use ($shippingCost) {
            $mock->shouldReceive('calculateWeight')->andReturn(500);
            $mock->shouldReceive('getServiceCost')->andReturn($shippingCost);
        });

        return app(TransactionService::class)->createOnlineTransaction(
            [['product_id' => $this->product->id, 'qty' => 2]],
            ['name' => 'Member', 'phone' => '081234567890', 'email' => 'member@example.test'],
            $delivery,
            $this->member->id,
        )->refresh();
    }

    private function delivery(): array
    {
        return [
            'method' => 'delivery',
            'destination_id' => 123,
            'courier' => 'jne',
            'service' => 'REG',
            'address' => 'Jl. Mawar 1',
        ];
    }

    public function test_delivery_order_stores_shipping_cost_and_service(): void
    {
        $transaction = $this->createOrder($this->delivery());

        $this->assertSame(15000, (int) $transaction->shipping_cost);
        $this->assertSame('REG', $transaction->shipping_service);
        $this->assertSame('jne', $transaction->courier);
        $this->assertSame(215000, (int) $transaction->total);
        $this->assertFalse(Schema::hasColumn('transactions', 'destination_id'));
    }

    public function test_free_shipping_is_stored_as_zero(): void
    {
        $transaction = $this->createOrder($this->delivery(), 0);

        $this->assertSame(0, (int) $transaction->shipping_cost);
        $this->assertSame(200000, (int) $transaction->total);
    }

    public function test_pickup_order_has_no_shipping_values(): void
    {
        $transaction = $this->createOrder(['method' => 'pickup']);

        $this->assertNull($transaction->shipping_cost);
        $this->assertNull($transaction->shipping_service);
        $this->assertNull($transaction->courier);
        $this->assertSame(200000, (int) $transaction->total);
    }

    public function test_admin_order_page_shows_shipping_cost_and_service(): void
    {
        $transaction = $this->createOrder($this->delivery());

        $this->actingAs($this->admin)
            ->get(route('admin.orders.show', $transaction))
            ->assertOk()
            ->assertSee('REG')
            ->assertSee('Shipping cost')
            ->assertSee('15.000');
    }

    public function test_admin_order_page_still_opens_for_an_old_order_without_the_new_columns(): void
    {
        $transaction = $this->createOrder($this->delivery());
        $transaction->update(['shipping_cost' => null, 'shipping_service' => null]);

        $this->actingAs($this->admin)
            ->get(route('admin.orders.show', $transaction))
            ->assertOk()
            ->assertSee('Shipping cost');
    }
}