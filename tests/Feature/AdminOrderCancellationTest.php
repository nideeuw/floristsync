<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\OrderCancellationService;
use App\Services\PaymentReversalService;
use App\Services\TransactionService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\Support\InteractsWithSeededRoles;
use Tests\TestCase;

class AdminOrderCancellationTest extends TestCase
{
    use InteractsWithSeededRoles, RefreshDatabase;

    private User $adminOrder;

    private User $member;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndMenus();

        $this->adminOrder = $this->userForRole(Role::ADMIN_ORDER);
        $this->member = $this->userForRole(Role::MEMBER);

        $category = Category::factory()->create();
        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'product_name' => 'Tulip Bouquet',
            'price' => 100000,
            'stock' => 15,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test')->byDefault();
            $mock->shouldReceive('cancelInvoice')->andReturnNull()->byDefault();
            $mock->shouldReceive('getStatus')->andReturn(null)->byDefault();
        });
    }

    private function createOnlineOrder(string $status = Transaction::PROCESSING, int $qty = 3): Transaction
    {
        $transaction = app(TransactionService::class)->createOnlineTransaction(
            [['product_id' => $this->product->id, 'qty' => $qty]],
            ['name' => 'John Doe', 'phone' => '08123456789', 'email' => 'john@example.test'],
            ['method' => 'pickup'],
            $this->member->id,
        );

        $transaction->update(['status' => $status]);
        $transaction->payment->update([
            'payment_method' => 'qris',
            'payment_status' => 'settlement',
            'payment_date' => now(),
        ]);

        return $transaction->fresh(['details.product', 'payment']);
    }

    public function test_admin_can_cancel_processing_online_order(): void
    {
        $this->mock(PaymentReversalService::class, function (MockInterface $mock) {
            $mock->shouldReceive('refund')->once();
        });

        $initialStock = $this->product->fresh()->stock;
        $order = $this->createOnlineOrder(Transaction::PROCESSING, 3);
        $this->assertSame($initialStock - 3, $this->product->fresh()->stock);

        $service = app(OrderCancellationService::class);
        $result = $service->cancel($order, $this->adminOrder, 'Out of requested flower batch');

        $this->assertSame(Transaction::CANCELLED, $result->status);
        $this->assertSame($this->adminOrder->id, (int) $result->cancelled_by);
        $this->assertNotNull($result->cancelled_at);
        $this->assertSame('Out of requested flower batch', $result->cancel_reason);

        // Stock restored back
        $this->assertSame($initialStock, $this->product->fresh()->stock);

        $this->assertDatabaseHas('stocks', [
            'product_id' => $this->product->id,
            'quantity_change' => 3,
            'change_type' => 'cancel',
        ]);

        // Notification sent to member
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->member->id,
            'title' => 'Order cancelled',
            'type' => 'order_status',
        ]);
    }

    public function test_admin_cannot_cancel_unpaid_order(): void
    {
        $order = $this->createOnlineOrder(Transaction::PENDING_PAYMENT);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Only orders in processing can be cancelled.');

        app(OrderCancellationService::class)->cancel($order, $this->adminOrder, 'Customer did not pay');
    }

    public function test_admin_cannot_cancel_order_ready_for_pickup_or_shipped(): void
    {
        $pickupOrder = $this->createOnlineOrder(Transaction::READY_FOR_PICKUP);

        try {
            app(OrderCancellationService::class)->cancel($pickupOrder, $this->adminOrder, 'Store reason');
            $this->fail('Expected DomainException for ready_for_pickup order.');
        } catch (DomainException $e) {
            $this->assertSame('Only orders in processing can be cancelled.', $e->getMessage());
        }

        $shippedOrder = $this->createOnlineOrder(Transaction::SHIPPED);

        try {
            app(OrderCancellationService::class)->cancel($shippedOrder, $this->adminOrder, 'Store reason');
            $this->fail('Expected DomainException for shipped order.');
        } catch (DomainException $e) {
            $this->assertSame('Only orders in processing can be cancelled.', $e->getMessage());
        }
    }

    public function test_admin_cannot_cancel_already_cancelled_order(): void
    {
        $order = $this->createOnlineOrder(Transaction::CANCELLED);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('This order has already been cancelled.');

        app(OrderCancellationService::class)->cancel($order, $this->adminOrder, 'Cancel again');
    }

    public function test_admin_cannot_cancel_non_online_order(): void
    {
        $posTx = Transaction::create([
            'transaction_type' => Transaction::TYPE_POS,
            'status' => Transaction::PROCESSING,
            'total' => 50000,
            'created_on' => now(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Only online orders can be cancelled.');

        app(OrderCancellationService::class)->cancel($posTx, $this->adminOrder, 'Cancel POS');
    }

    public function test_cancel_requires_a_reason(): void
    {
        $order = $this->createOnlineOrder(Transaction::PROCESSING);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cancellation reason is required.');

        app(OrderCancellationService::class)->cancel($order, $this->adminOrder, '   ');
    }

    public function test_controller_cancel_action_cancels_order_and_redirects(): void
    {
        $this->mock(PaymentReversalService::class, function (MockInterface $mock) {
            $mock->shouldReceive('refund')->once();
        });

        $order = $this->createOnlineOrder(Transaction::PROCESSING, 2);

        $response = $this->actingAs($this->adminOrder)
            ->from(route('admin.orders.cancellations'))
            ->patch(route('admin.orders.cancellations.cancel', $order), [
                'reason' => 'Store flower stock damaged during storage',
            ]);

        $response->assertRedirect(route('admin.orders.cancellations'));
        $response->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(Transaction::CANCELLED, $order->status);
        $this->assertSame('Store flower stock damaged during storage', $order->cancel_reason);
        $this->assertSame($this->adminOrder->id, (int) $order->cancelled_by);
    }

    public function test_controller_validates_reason(): void
    {
        $order = $this->createOnlineOrder(Transaction::PROCESSING);

        $response = $this->actingAs($this->adminOrder)
            ->patch(route('admin.orders.cancellations.cancel', $order), [
                'reason' => '',
            ]);

        $response->assertSessionHasErrors('reason');
        $this->assertSame(Transaction::PROCESSING, $order->fresh()->status);
    }

    public function test_unauthorized_roles_cannot_access_cancel_route(): void
    {
        $order = $this->createOnlineOrder(Transaction::PROCESSING);

        // Member gets 403
        $this->actingAs($this->member)
            ->patch(route('admin.orders.cancellations.cancel', $order), ['reason' => 'Unauthorized'])
            ->assertForbidden();

        // Cashier gets 403
        $cashier = $this->userForRole(Role::CASHIER);
        $this->actingAs($cashier)
            ->patch(route('admin.orders.cancellations.cancel', $order), ['reason' => 'Unauthorized'])
            ->assertForbidden();

        // Guest redirected to admin login
        $this->app['auth']->guard('web')->logout();
        $this->patch(route('admin.orders.cancellations.cancel', $order), ['reason' => 'Guest'])
            ->assertRedirect(route('admin.login'));
    }
}
