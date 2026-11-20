<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use Database\Factories\ProductFactory;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class ShippingWeightTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    /** @var int[] weights posted to RajaOngkir in order */
    private array $sentWeights = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        config([
            'services.rajaongkir.base_url' => 'https://rajaongkir.test/api/v1',
            'services.rajaongkir.api_key' => 'test-key',
            'services.rajaongkir.origin_id' => 501,
        ]);

        $this->member = User::factory()->create([
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test')->byDefault();
            $mock->shouldReceive('cancelInvoice')->andReturnNull()->byDefault();
            $mock->shouldReceive('getStatus')->andReturn(null)->byDefault();
        });
    }

    private function fakeCost(int $cost = 20000): void
    {
        $this->sentWeights = [];

        Http::fake([
            'calculate/domestic-cost' => function ($request) use ($cost) {
                $this->sentWeights[] = (int) ($request->data()['weight'] ?? 0);

                return Http::response([
                    'meta' => ['code' => 200],
                    'data' => [
                        [
                            'code' => 'jne',
                            'service' => 'REG',
                            'description' => 'Reguler',
                            'cost' => $cost,
                            'etd' => '2',
                        ],
                    ],
                ], 200);
            },
        ]);
    }

    private function makeProduct(array $attributes = []): Product
    {
        $product = Product::create(array_merge([
            'category_id' => Category::factory()->create()->id,
            'product_name' => 'Test Bouquet',
            'price' => 100000,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ], $attributes));

        ProductFactory::setStock($product, 10);

        return $product;
    }

    private function requestCost(array $items): void
    {
        $this->actingAs($this->member)->post('/api/shipping/cost', [
            'destination_id' => 123,
            'courier' => 'jne',
            'items' => $items,
        ])->assertOk();
    }

    public function test_products_with_different_weights_send_different_weights_to_rajaongkir(): void
    {
        $light = $this->makeProduct(['product_name' => 'Small Bouquet', 'weight_gram' => 250]);
        $heavy = $this->makeProduct(['product_name' => 'Standing Flower', 'weight_gram' => 3000]);

        $this->fakeCost();

        $this->requestCost([['product_id' => $light->id, 'qty' => 2]]);
        $this->requestCost([['product_id' => $heavy->id, 'qty' => 1]]);

        $this->assertSame([500, 3000], $this->sentWeights);
    }

    public function test_product_without_weight_falls_back_to_the_config_default(): void
    {
        config(['floristsync.default_item_weight_gram' => 800]);

        $noWeight = $this->makeProduct(['product_name' => 'No Weight', 'weight_gram' => null]);

        $this->fakeCost();
        $this->requestCost([['product_id' => $noWeight->id, 'qty' => 3]]);

        $this->assertSame([2400], $this->sentWeights);
    }

    public function test_server_recompute_equals_the_api_quote_and_ignores_a_browser_cost(): void
    {
        $product = $this->makeProduct(['weight_gram' => 250]);

        $this->fakeCost(20000);

        // The quote the browser sees.
        $this->requestCost([['product_id' => $product->id, 'qty' => 2]]);
        $this->assertSame([500], $this->sentWeights);

        // A lying browser cost must be ignored: the server recomputes the same weight and cost.
        $this->actingAs($this->member)
            ->post(route('cart.add', $product), ['qty' => 2])
            ->assertRedirect();

        $this->actingAs($this->member)->post(route('checkout.store'), [
            'name' => 'Member',
            'phone' => '081234567890',
            'fulfillment' => 'delivery',
            'address' => 'Jl. Melati No. 1',
            'destination_id' => 123,
            'courier' => 'jne',
            'service' => 'REG',
            'shipping_cost' => 1,
        ])->assertStatus(303);

        $transaction = Transaction::where('user_id', $this->member->id)->latest('id')->firstOrFail();

        $this->assertSame(20000, (int) $transaction->shipping_cost);
        $this->assertSame('REG', $transaction->shipping_service);
        $this->assertSame(220000, (int) $transaction->total);
        // The recompute hit the same weight, so no second RajaOngkir call with another weight happened.
        $this->assertSame([500], $this->sentWeights);
    }
}
