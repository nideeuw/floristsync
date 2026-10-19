<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use App\Services\ShippingService;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class CustomerTrackTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $otherMember;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $memberRole = Role::where('role_name', Role::MEMBER)->firstOrFail();

        $this->member = User::factory()->create([
            'name' => 'Alice Member',
            'email' => 'alice@example.com',
            'phone' => '0811111111',
            'role_id' => $memberRole->id,
            'is_active' => true,
        ]);

        $this->otherMember = User::factory()->create([
            'name' => 'Bob Member',
            'email' => 'bob@example.com',
            'phone' => '0822222222',
            'role_id' => $memberRole->id,
            'is_active' => true,
        ]);

        $category = Category::create([
            'category_name' => 'Flowers',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'product_name' => 'Red Roses Bouquet',
            'price' => 120000,
            'stock' => 20,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);
    }

    public function test_page_renders_with_zero_orders(): void
    {
        $response = $this->actingAs($this->member)->get(route('track'));

        $response->assertOk();
        $response->assertSee('No orders yet');
        $response->assertSee('Browse flowers');
    }

    public function test_page_renders_with_pending_payment_order(): void
    {
        $transaction = $this->createOrder(
            user: $this->member,
            status: Transaction::PENDING_PAYMENT,
            paymentStatus: 'pending'
        );

        $response = $this->actingAs($this->member)->get(route('track'));

        $response->assertOk();
        $response->assertSee($transaction->order_number);
        $response->assertSee('Pay now');
        $response->assertSee(route('track.pay', $transaction));
    }

    public function test_page_renders_with_processing_order(): void
    {
        $transaction = $this->createOrder(
            user: $this->member,
            status: Transaction::PROCESSING,
            paymentStatus: 'settlement'
        );

        $response = $this->actingAs($this->member)->get(route('track'));

        $response->assertOk();
        $response->assertSee($transaction->order_number);
        $response->assertSee('Cancel order');
    }

    public function test_page_renders_with_delivery_order_having_shipping_cost_and_service(): void
    {
        $transaction = $this->createOrder(
            user: $this->member,
            status: Transaction::PROCESSING,
            paymentStatus: 'settlement',
            isDelivery: true,
            courier: 'jne',
            shippingCost: 25000,
            shippingService: 'REG',
            trackingNumber: 'JNE123456789'
        );

        $response = $this->actingAs($this->member)->get(route('track', ['order' => $transaction->order_number]));

        $response->assertOk();
        $response->assertSee($transaction->order_number);
        $response->assertSee('delivery');
        $response->assertSee('JNE');
        $response->assertSee('JNE123456789');
    }

    public function test_waybill_tracking_failure_never_fails_the_page(): void
    {
        $this->mock(ShippingService::class, function (MockInterface $mock) {
            $mock->shouldReceive('trackWaybill')
                ->andThrow(new RuntimeException('Shipping service is unavailable. Please try again later.'));
        });

        $transaction = $this->createOrder(
            user: $this->member,
            status: Transaction::SHIPPED,
            paymentStatus: 'settlement',
            isDelivery: true,
            courier: 'jne',
            shippingCost: 25000,
            shippingService: 'REG',
            trackingNumber: 'JNE999999999'
        );

        $response = $this->actingAs($this->member)->get(route('track', ['order' => $transaction->order_number]));

        $response->assertOk();
        $response->assertSee($transaction->order_number);
        $response->assertSee('JNE');
        $response->assertSee('JNE999999999');
    }

    public function test_member_must_only_see_their_own_orders(): void
    {
        $ownOrder = $this->createOrder(
            user: $this->member,
            status: Transaction::PROCESSING,
            paymentStatus: 'settlement'
        );

        $otherOrder = $this->createOrder(
            user: $this->otherMember,
            status: Transaction::PROCESSING,
            paymentStatus: 'settlement'
        );

        // Member visits order list
        $response = $this->actingAs($this->member)->get(route('track'));

        $response->assertOk();
        $response->assertSee($ownOrder->order_number);
        $response->assertDontSee($otherOrder->order_number);

        // Member tries to query other member's order
        $otherOrderResponse = $this->actingAs($this->member)->get(route('track', ['order' => $otherOrder->order_number]));

        $otherOrderResponse->assertOk();
        $otherOrderResponse->assertSee('Order not found');
        $otherOrderResponse->assertDontSee($otherOrder->details->first()->product->product_name);
    }

    private function createOrder(
        User $user,
        string $status,
        string $paymentStatus,
        bool $isDelivery = false,
        ?string $courier = null,
        ?int $shippingCost = null,
        ?string $shippingService = null,
        ?string $trackingNumber = null
    ): Transaction {
        $subtotal = 120000;
        $total = $subtotal + ($shippingCost ?? 0);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_type' => Transaction::TYPE_ONLINE,
            'status' => $status,
            'shipping_address' => $isDelivery ? 'Jl. Kenanga No. 5' : null,
            'courier' => $courier,
            'shipping_cost' => $shippingCost,
            'shipping_service' => $shippingService,
            'tracking_number' => $trackingNumber,
            'total' => $total,
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'unit_price' => $subtotal,
            'subtotal' => $subtotal,
        ]);

        Payment::create([
            'transaction_id' => $transaction->id,
            'midtrans_order_id' => 'FS-TEST-'.$transaction->id.'-'.uniqid(),
            'payment_method' => 'midtrans',
            'payment_status' => $paymentStatus,
            'amount' => $total,
            'payment_date' => $paymentStatus === 'settlement' ? now() : null,
        ]);

        return $transaction;
    }
}
