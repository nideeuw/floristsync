<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\ShippingService;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use Tests\TestCase;

class CourierServiceLabelTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $adminOrder;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $this->member = User::factory()->create([
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        $this->adminOrder = User::factory()->create([
            'role_id' => Role::where('role_name', Role::ADMIN_ORDER)->value('id'),
            'is_active' => true,
        ]);

        $category = Category::factory()->create(['category_name' => 'Bouquets', 'is_active' => true]);

        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'product_name' => 'Tulip Bouquet',
            'price' => 150000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);
    }

    public function test_format_service_label_formats_readable_names_with_estimates(): void
    {
        $this->assertSame(
            'JNE City Courier (1 day)',
            ShippingService::formatServiceLabel('jne', 'CTC', 'City Courier', '1')
        );

        $this->assertSame(
            'JNE City Courier YES (1 day)',
            ShippingService::formatServiceLabel('jne', 'CTCYES', 'City Courier YES', '1')
        );

        $this->assertSame(
            'JNE Trucking < 130 kg (3-5 days)',
            ShippingService::formatServiceLabel('jne', 'JTR<130', 'JNE Trucking < 130 kg', '3-5')
        );

        $this->assertSame(
            'JNE Reguler (2 days)',
            ShippingService::formatServiceLabel('jne', 'REG', 'Reguler', '2')
        );

        $this->assertSame(
            'TIKI Over Night Service (1 day)',
            ShippingService::formatServiceLabel('tiki', 'ONS', 'Over Night Service', '1')
        );

        $this->assertSame(
            'SiCepat SiUntung (1-2 days)',
            ShippingService::formatServiceLabel('sicepat', 'SIUNT', 'SiUntung', '1-2 hari')
        );

        $this->assertSame(
            'POS Indonesia Reguler (2-3 days)',
            ShippingService::formatServiceLabel('pos', 'Pos Reguler', 'Pos Reguler', '2-3')
        );
    }

    public function test_format_service_label_uses_dictionary_when_description_is_null_or_code(): void
    {
        $this->assertSame('JNE City Courier', ShippingService::formatServiceLabel('jne', 'CTC'));
        $this->assertSame('JNE City Courier YES', ShippingService::formatServiceLabel('jne', 'CTCYES'));
        $this->assertSame('JNE Trucking (<130kg)', ShippingService::formatServiceLabel('jne', 'JTR<130'));
        $this->assertSame('JNE Reguler', ShippingService::formatServiceLabel('jne', 'REG'));
        $this->assertSame('JNE Yakin Esok Sampai', ShippingService::formatServiceLabel('jne', 'YES'));
        $this->assertSame('TIKI Over Night Service', ShippingService::formatServiceLabel('tiki', 'ONS'));
        $this->assertSame('SiCepat SiUntung', ShippingService::formatServiceLabel('sicepat', 'SIUNT'));
        $this->assertSame('POS Indonesia Reguler', ShippingService::formatServiceLabel('pos', 'Pos Reguler'));
    }

    public function test_transaction_model_shipping_service_label_accessor(): void
    {
        $order = new Transaction([
            'courier' => 'jne',
            'shipping_service' => 'CTC',
        ]);
        $this->assertSame('JNE City Courier', $order->shipping_service_label);

        $orderYes = new Transaction([
            'courier' => 'jne',
            'shipping_service' => 'CTCYES',
        ]);
        $this->assertSame('JNE City Courier YES', $orderYes->shipping_service_label);

        $orderJtr = new Transaction([
            'courier' => 'jne',
            'shipping_service' => 'JTR<130',
        ]);
        $this->assertSame('JNE Trucking (<130kg)', $orderJtr->shipping_service_label);

        $pickup = new Transaction([
            'courier' => null,
            'shipping_service' => null,
        ]);
        $this->assertNull($pickup->shipping_service_label);
    }

    public function test_calculate_cost_includes_label_in_returned_options(): void
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
                        'name' => 'Jalur Nugraha Ekakurir (JNE)',
                        'code' => 'jne',
                        'service' => 'CTC',
                        'description' => 'City Courier',
                        'cost' => 10000,
                        'etd' => '1',
                    ],
                    [
                        'name' => 'Jalur Nugraha Ekakurir (JNE)',
                        'code' => 'jne',
                        'service' => 'CTCYES',
                        'description' => 'City Courier YES',
                        'cost' => 18000,
                        'etd' => '1',
                    ],
                    [
                        'name' => 'Jalur Nugraha Ekakurir (JNE)',
                        'code' => 'jne',
                        'service' => 'JTR<130',
                        'description' => 'JNE Trucking < 130 kg',
                        'cost' => 25000,
                        'etd' => '3-5',
                    ],
                ],
            ]),
        ]);

        $options = app(ShippingService::class)->calculateCost(10, 1000, 'jne');

        $this->assertCount(3, $options);
        $this->assertSame('JNE City Courier (1 day)', $options[0]['label']);
        $this->assertSame('CTC', $options[0]['service']);
        $this->assertSame('JNE City Courier YES (1 day)', $options[1]['label']);
        $this->assertSame('CTCYES', $options[1]['service']);
        $this->assertSame('JNE Trucking < 130 kg (3-5 days)', $options[2]['label']);
        $this->assertSame('JTR<130', $options[2]['service']);
    }

    public function test_checkout_stores_raw_service_code(): void
    {
        config([
            'services.rajaongkir.base_url' => 'https://rajaongkir.test',
            'services.rajaongkir.api_key' => 'test-key',
            'services.rajaongkir.origin_id' => 115,
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test');
            $mock->shouldReceive('cancelInvoice')->andReturnNull();
        });

        $this->mock(ShippingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('calculateWeight')->andReturn(500);
            $mock->shouldReceive('getServiceCost')->andReturn(12000);
        });

        $this->actingAs($this->member)
            ->post(route('cart.add', $this->product), ['qty' => 1])
            ->assertRedirect();

        $response = $this->actingAs($this->member)->post(route('checkout.store'), [
            'name' => 'Alice Customer',
            'phone' => '081234567890',
            'fulfillment' => 'delivery',
            'destination_id' => 50,
            'courier' => 'jne',
            'service' => 'CTC',
            'address' => 'Jl. Merbabu No. 10',
        ]);

        $response->assertOk();

        $order = Transaction::latest('id')->first();
        $this->assertNotNull($order);
        // The raw code must be preserved in the database
        $this->assertSame('CTC', $order->shipping_service);
        $this->assertSame('jne', $order->courier);
        $this->assertSame('JNE City Courier', $order->shipping_service_label);
    }

    public function test_readable_label_is_displayed_on_order_detail_invoice_and_admin_shipping(): void
    {
        $order = Transaction::create([
            'user_id' => $this->member->id,
            'transaction_type' => Transaction::TYPE_ONLINE,
            'status' => Transaction::PROCESSING,
            'shipping_address' => "Alice Customer (081234567890)\nJl. Bunga Melati No. 8",
            'courier' => 'jne',
            'shipping_cost' => 12000,
            'shipping_service' => 'CTC',
            'tracking_number' => 'JNE123456789',
            'total' => 162000,
        ]);

        TransactionDetail::create([
            'transaction_id' => $order->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'unit_price' => 150000,
            'subtotal' => 150000,
        ]);

        Payment::create([
            'transaction_id' => $order->id,
            'midtrans_order_id' => 'FS-TEST-LABEL-'.$order->id,
            'payment_method' => 'midtrans',
            'payment_status' => 'settlement',
            'amount' => 162000,
            'payment_date' => now(),
        ]);

        // 1. Order Detail page
        $this->actingAs($this->member)
            ->get(route('track', ['order' => $order->order_number]))
            ->assertOk()
            ->assertSee('JNE City Courier');

        // 2. Invoice page
        $this->actingAs($this->member)
            ->get(route('track.invoice', $order))
            ->assertOk()
            ->assertSee('JNE City Courier');

        // 3. Admin Shipping page
        $this->actingAs($this->adminOrder)
            ->get(route('admin.orders.shipping'))
            ->assertOk()
            ->assertSee('JNE City Courier');
    }
}
