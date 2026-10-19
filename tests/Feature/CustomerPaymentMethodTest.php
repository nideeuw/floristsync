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
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class CustomerPaymentMethodTest extends TestCase
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
            'phone' => '08123456789',
            'role_id' => $memberRole->id,
            'is_active' => true,
        ]);

        $this->otherMember = User::factory()->create([
            'name' => 'Bob Member',
            'email' => 'bob@example.com',
            'phone' => '08987654321',
            'role_id' => $memberRole->id,
            'is_active' => true,
        ]);

        $category = Category::create([
            'category_name' => 'Flowers',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'product_name' => 'Rose Bouquet',
            'price' => 150000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getStatus')->andReturn(null)->byDefault();
            $mock->shouldReceive('cancelInvoice')->andReturnNull()->byDefault();
        });
    }

    private function createPendingOrder(User $user, int $qty = 1, ?string $createdOn = null): Transaction
    {
        $total = $this->product->price * $qty;

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_type' => Transaction::TYPE_ONLINE,
            'status' => Transaction::PENDING_PAYMENT,
            'total' => $total,
            'created_on' => $createdOn ?? now(),
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'qty' => $qty,
            'unit_price' => $this->product->price,
            'subtotal' => $total,
        ]);

        Payment::create([
            'transaction_id' => $transaction->id,
            'midtrans_order_id' => 'FS-' . $transaction->id . '-TEST01',
            'payment_status' => 'pending',
            'amount' => $total,
        ]);

        return $transaction;
    }

    public function test_member_can_view_payment_page(): void
    {
        $transaction = $this->createPendingOrder($this->member);

        $response = $this->actingAs($this->member)->get(route('track.pay', $transaction));

        $response->assertOk();
        $response->assertSee('Complete Your Payment');
        $response->assertSee($transaction->order_number);
        $response->assertSee('QRIS');
        $response->assertSee('GoPay');
        $response->assertSee('ShopeePay');
        $response->assertSee('Credit / Debit Card');
    }

    public function test_member_can_charge_qris(): void
    {
        $transaction = $this->createPendingOrder($this->member);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('chargeCoreApi')
                ->once()
                ->withArgs(function ($tx, $method, $customer) {
                    return $method === 'qris';
                })
                ->andReturn([
                    'actions' => [
                        [
                            'name' => 'generate-qr-code',
                            'method' => 'GET',
                            'url' => 'https://api.sandbox.midtrans.com/v2/qris/12345/qr-code',
                        ],
                    ],
                    'qr_string' => '00020101021226540014ID.LINKAJA.WWW...',
                ]);
        });

        $response = $this->actingAs($this->member)->postJson(
            route('api.customer.orders.charge', $transaction),
            ['method' => 'qris']
        );

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'method' => 'qris',
                'qr_url' => 'https://api.sandbox.midtrans.com/v2/qris/12345/qr-code',
                'qr_string' => '00020101021226540014ID.LINKAJA.WWW...',
            ],
        ]);

        $payment = $transaction->fresh()->payment;
        $this->assertSame('qris', $payment->payment_method);
        $this->assertSame('https://api.sandbox.midtrans.com/v2/qris/12345/qr-code', $payment->snap_token);
        $this->assertSame('00020101021226540014ID.LINKAJA.WWW...', $payment->payment_proof);
    }

    public function test_member_can_charge_gopay(): void
    {
        $transaction = $this->createPendingOrder($this->member);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('chargeCoreApi')
                ->once()
                ->withArgs(function ($tx, $method, $customer) {
                    return $method === 'gopay';
                })
                ->andReturn([
                    'actions' => [
                        [
                            'name' => 'generate-qr-code',
                            'method' => 'GET',
                            'url' => 'https://api.sandbox.midtrans.com/v2/gopay/67890/qr-code',
                        ],
                        [
                            'name' => 'deeplink-redirect',
                            'method' => 'GET',
                            'url' => 'https://simulator.sandbox.midtrans.com/gopay/partner/app/payment-pin?id=67890',
                        ],
                    ],
                ]);
        });

        $response = $this->actingAs($this->member)->postJson(
            route('api.customer.orders.charge', $transaction),
            ['method' => 'gopay']
        );

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'method' => 'gopay',
                'qr_url' => 'https://api.sandbox.midtrans.com/v2/gopay/67890/qr-code',
                'deeplink' => 'https://simulator.sandbox.midtrans.com/gopay/partner/app/payment-pin?id=67890',
            ],
        ]);

        $payment = $transaction->fresh()->payment;
        $this->assertSame('gopay', $payment->payment_method);
        $this->assertSame('https://api.sandbox.midtrans.com/v2/gopay/67890/qr-code', $payment->snap_token);
    }

    public function test_member_can_charge_shopeepay(): void
    {
        $transaction = $this->createPendingOrder($this->member);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('chargeCoreApi')
                ->once()
                ->withArgs(function ($tx, $method, $customer) {
                    return $method === 'shopeepay';
                })
                ->andReturn([
                    'actions' => [
                        [
                            'name' => 'deeplink-redirect',
                            'method' => 'GET',
                            'url' => 'https://simulator.sandbox.midtrans.com/shopeepay/partner/app/payment-pin?id=55555',
                        ],
                    ],
                ]);
        });

        $response = $this->actingAs($this->member)->postJson(
            route('api.customer.orders.charge', $transaction),
            ['method' => 'shopeepay']
        );

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'method' => 'shopeepay',
                'deeplink' => 'https://simulator.sandbox.midtrans.com/shopeepay/partner/app/payment-pin?id=55555',
            ],
        ]);

        $payment = $transaction->fresh()->payment;
        $this->assertSame('shopeepay', $payment->payment_method);
        $this->assertSame('https://simulator.sandbox.midtrans.com/shopeepay/partner/app/payment-pin?id=55555', $payment->payment_proof);
    }

    public function test_member_can_charge_credit_card_via_snap(): void
    {
        $transaction = $this->createPendingOrder($this->member);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')
                ->once()
                ->withArgs(function ($tx, $customer, $enabledPayments) {
                    return $enabledPayments === ['credit_card'];
                })
                ->andReturn('test-card-snap-token');
        });

        $response = $this->actingAs($this->member)->postJson(
            route('api.customer.orders.charge', $transaction),
            ['method' => 'card']
        );

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'method' => 'credit_card',
                'snap_token' => 'test-card-snap-token',
            ],
        ]);

        $payment = $transaction->fresh()->payment;
        $this->assertSame('credit_card', $payment->payment_method);
        $this->assertSame('test-card-snap-token', $payment->snap_token);
    }

    public function test_changing_payment_method_cancels_previous_charge_and_generates_new_order_id(): void
    {
        $transaction = $this->createPendingOrder($this->member);
        $oldOrderId = $transaction->payment->midtrans_order_id;

        // Step 1: Set initial method to QRIS
        $transaction->payment->update([
            'payment_method' => 'qris',
            'snap_token' => 'https://qris.test/1',
            'payment_proof' => 'raw-qr-1',
        ]);

        // Step 2: Switch to GoPay. Expect cancel on old order ID, then charging GoPay
        $this->mock(MidtransService::class, function (MockInterface $mock) use ($oldOrderId) {
            $mock->shouldReceive('getStatus')
                ->with($oldOrderId)
                ->andReturn(['transaction_status' => 'pending']);

            $mock->shouldReceive('cancelTransaction')
                ->once()
                ->with($oldOrderId)
                ->andReturnNull();

            $mock->shouldReceive('chargeCoreApi')
                ->once()
                ->withArgs(function ($tx, $method) {
                    return $method === 'gopay';
                })
                ->andReturn([
                    'actions' => [
                        [
                            'name' => 'generate-qr-code',
                            'method' => 'GET',
                            'url' => 'https://gopay.test/2',
                        ],
                    ],
                ]);
        });

        $response = $this->actingAs($this->member)->postJson(
            route('api.customer.orders.charge', $transaction),
            ['method' => 'gopay']
        );

        $response->assertOk();
        $payment = $transaction->fresh()->payment;
        $this->assertSame('gopay', $payment->payment_method);
        $this->assertNotSame($oldOrderId, $payment->midtrans_order_id);
        $this->assertSame('https://gopay.test/2', $payment->snap_token);
    }

    public function test_member_can_cancel_active_charge(): void
    {
        $transaction = $this->createPendingOrder($this->member);
        $oldOrderId = $transaction->payment->midtrans_order_id;
        $transaction->payment->update(['payment_method' => 'qris']);

        $this->mock(MidtransService::class, function (MockInterface $mock) use ($oldOrderId) {
            $mock->shouldReceive('getStatus')
                ->with($oldOrderId)
                ->andReturn(['transaction_status' => 'pending']);

            $mock->shouldReceive('cancelTransaction')
                ->once()
                ->with($oldOrderId)
                ->andReturnNull();
        });

        $response = $this->actingAs($this->member)->postJson(
            route('api.customer.orders.cancel', $transaction)
        );

        $response->assertOk();
        $payment = $transaction->fresh()->payment;
        $this->assertNull($payment->payment_method);
        $this->assertNull($payment->snap_token);
        $this->assertNotSame($oldOrderId, $payment->midtrans_order_id);
    }

    public function test_status_endpoint_queries_midtrans_directly_and_marks_paid_on_settlement(): void
    {
        $transaction = $this->createPendingOrder($this->member);
        $orderId = $transaction->payment->midtrans_order_id;

        $this->mock(MidtransService::class, function (MockInterface $mock) use ($orderId, $transaction) {
            $mock->shouldReceive('getStatus')
                ->once()
                ->with($orderId)
                ->andReturn([
                    'order_id' => $orderId,
                    'transaction_status' => 'settlement',
                    'fraud_status' => null,
                    'payment_type' => 'gopay',
                    'gross_amount' => (string) $transaction->total,
                ]);
        });

        $response = $this->actingAs($this->member)->getJson(
            route('api.customer.orders.status', $transaction)
        );

        $response->assertOk();
        $response->assertJson([
            'status' => 'settlement',
            'is_paid' => true,
            'is_failed' => false,
        ]);

        $this->assertSame(Transaction::PROCESSING, $transaction->fresh()->status);
        $this->assertNotNull($transaction->fresh()->payment->payment_date);
    }

    public function test_status_endpoint_queries_midtrans_and_marks_cancelled_on_expire(): void
    {
        $transaction = $this->createPendingOrder($this->member);
        $orderId = $transaction->payment->midtrans_order_id;

        $this->mock(MidtransService::class, function (MockInterface $mock) use ($orderId, $transaction) {
            $mock->shouldReceive('getStatus')
                ->once()
                ->with($orderId)
                ->andReturn([
                    'order_id' => $orderId,
                    'transaction_status' => 'expire',
                    'fraud_status' => null,
                    'payment_type' => 'qris',
                    'gross_amount' => (string) $transaction->total,
                ]);
            $mock->shouldReceive('cancelInvoice')->byDefault();
        });

        $response = $this->actingAs($this->member)->getJson(
            route('api.customer.orders.status', $transaction)
        );

        $response->assertOk();
        $response->assertJson([
            'status' => 'expire',
            'is_paid' => false,
            'is_failed' => true,
        ]);

        $this->assertSame(Transaction::CANCELLED, $transaction->fresh()->status);
    }

    public function test_member_cannot_charge_other_users_order(): void
    {
        $transaction = $this->createPendingOrder($this->otherMember);

        $response = $this->actingAs($this->member)->postJson(
            route('api.customer.orders.charge', $transaction),
            ['method' => 'qris']
        );

        $response->assertForbidden();
    }

    public function test_member_cannot_charge_expired_order(): void
    {
        // 65 minutes ago (past 60-minute window)
        $transaction = $this->createPendingOrder($this->member, 1, now()->subMinutes(65));

        $response = $this->actingAs($this->member)->postJson(
            route('api.customer.orders.charge', $transaction),
            ['method' => 'qris']
        );

        $response->assertStatus(422);
        $response->assertJson(['message' => 'The payment time for this order has expired. Please place a new order.']);
    }

    public function test_cannot_charge_invalid_method(): void
    {
        $transaction = $this->createPendingOrder($this->member);

        $response = $this->actingAs($this->member)->postJson(
            route('api.customer.orders.charge', $transaction),
            ['method' => 'bank_transfer']
        );

        $response->assertStatus(422);
    }
}
