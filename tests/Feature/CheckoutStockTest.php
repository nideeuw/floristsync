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
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class CheckoutStockTest extends TestCase
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

        $category = Category::factory()->create();

        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'product_name' => 'Sunflower Bouquet',
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

    public function test_checkout_decrements_stock(): void
    {
        $this->actingAs($this->member)
            ->post(route('cart.add', $this->product), ['qty' => 2])
            ->assertRedirect();

        $response = $this->actingAs($this->member)->post(route('checkout.store'), [
            'name' => 'Member',
            'phone' => '081234567890',
            'fulfillment' => 'pickup',
        ]);

        $response->assertOk();

        $this->assertSame(8, $this->product->fresh()->stock);

        $transaction = Transaction::where('user_id', $this->member->id)->latest('id')->firstOrFail();
        $this->assertSame(Transaction::TYPE_ONLINE, $transaction->transaction_type);
        $this->assertSame(Transaction::PENDING_PAYMENT, $transaction->status);
        $this->assertSame(2, (int) $transaction->details()->sum('qty'));
        $this->assertNotNull($transaction->payment);
        $this->assertSame('pending', $transaction->payment->payment_status);
        $this->assertSame(200000, (int) $transaction->total);
    }

    public function test_checkout_rejects_out_of_stock(): void
    {
        $this->product->update(['stock' => 1]);

        try {
            app(TransactionService::class)->createOnlineTransaction(
                [['product_id' => $this->product->id, 'qty' => 5]],
                ['name' => 'Member', 'phone' => '081234567890'],
                ['method' => 'pickup'],
                $this->member->id,
            );

            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        $this->assertSame(1, $this->product->fresh()->stock);
        $this->assertSame(0, Transaction::where('user_id', $this->member->id)->count());
    }

    public function test_checkout_rejects_unavailable_product(): void
    {
        $this->product->update(['status' => Product::STATUS_UNAVAILABLE]);

        try {
            app(TransactionService::class)->createOnlineTransaction(
                [['product_id' => $this->product->id, 'qty' => 1]],
                ['name' => 'Member', 'phone' => '081234567890'],
                ['method' => 'pickup'],
                $this->member->id,
            );

            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('not found or are not available', $e->getMessage());
        }

        $this->assertSame(10, $this->product->fresh()->stock);
    }

    public function test_stock_never_goes_below_zero(): void
    {
        $this->product->update(['stock' => 2]);

        $service = app(TransactionService::class);
        $items = [['product_id' => $this->product->id, 'qty' => 2]];
        $customer = ['name' => 'Member', 'phone' => '081234567890'];
        $delivery = ['method' => 'pickup'];

        $service->createOnlineTransaction($items, $customer, $delivery, $this->member->id);

        $this->assertSame(0, $this->product->fresh()->stock);

        try {
            $service->createOnlineTransaction($items, $customer, $delivery, $this->member->id);

            $this->fail('Expected DomainException was not thrown.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        $this->assertSame(0, $this->product->fresh()->stock);
    }

    public function test_delivery_checkout_includes_shipping_cost(): void
    {
        config([
            'services.rajaongkir.base_url' => 'https://rajaongkir.test',
            'services.rajaongkir.api_key' => 'test-key',
            'services.rajaongkir.origin_id' => 115,
        ]);

        Http::fake([
            'calculate/domestic-cost' => Http::response([
                'meta' => ['code' => 200],
                'data' => [
                    [
                        'courier' => 'jne',
                        'code' => 'jne',
                        'service' => 'REG',
                        'description' => 'Reguler',
                        'cost' => 20000,
                        'etd' => '2',
                    ],
                ],
            ]),
        ]);

        $this->actingAs($this->member)
            ->post(route('cart.add', $this->product), ['qty' => 1])
            ->assertRedirect();

        $response = $this->actingAs($this->member)->post(route('checkout.store'), [
            'name' => 'Member',
            'phone' => '081234567890',
            'fulfillment' => 'delivery',
            'address' => 'Jl. Melati No. 1',
            'destination_id' => 23,
            'courier' => 'jne',
            'service' => 'REG',
        ]);

        $response->assertOk();

        $transaction = Transaction::where('user_id', $this->member->id)->latest('id')->firstOrFail();
        $this->assertSame(120000, (int) $transaction->total);
        $this->assertSame('jne', $transaction->courier);
        $this->assertStringContainsString('Jl. Melati No. 1', (string) $transaction->shipping_address);
        $this->assertSame(9, $this->product->fresh()->stock);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'calculate/domestic-cost'));
    }
}
