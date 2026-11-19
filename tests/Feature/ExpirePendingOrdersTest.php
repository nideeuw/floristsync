<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\NotificationService;
use App\Services\TransactionService;
use Database\Seeders\RolesMenusSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery\MockInterface;
use Tests\TestCase;
use Throwable;

class ExpirePendingOrdersTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Product $product;

    /** @var array<string, array|Throwable|null> What Midtrans answers per order id (missing = no record). */
    private array $remote = [];

    /** @var list<string> Order ids that were checked against Midtrans. */
    private array $statusChecks = [];

    /** @var list<string> Order ids whose invoice was cancelled at Midtrans. */
    private array $cancelledInvoices = [];

    /** @var list<string> Titles of the notifications sent to roles. */
    private array $roleNotices = [];

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
            'product_name' => 'Daisy Bundle',
            'price' => 100000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test');
            $mock->shouldReceive('cancelInvoice')->andReturnUsing(function (string $orderId) {
                $this->cancelledInvoices[] = $orderId;
            });
            $mock->shouldReceive('getStatus')->andReturnUsing(function (string $orderId) {
                $this->statusChecks[] = $orderId;
                $answer = $this->remote[$orderId] ?? null;

                if ($answer instanceof Throwable) {
                    throw $answer;
                }

                return $answer;
            });
        });

        $notifications = $this->spy(NotificationService::class);
        $notifications->shouldReceive('notifyRoles')->andReturnUsing(function (...$args) {
            $this->roleNotices[] = (string) ($args[1] ?? '');
        });
    }

    private function createOrder(int $qty = 1): Transaction
    {
        return app(TransactionService::class)->createOnlineTransaction(
            [['product_id' => $this->product->id, 'qty' => $qty]],
            ['name' => 'Member', 'phone' => '081234567890', 'email' => 'member@example.test'],
            ['method' => 'pickup'],
            $this->member->id,
        );
    }

    /** Creates an order and moves it past the payment window. */
    private function createOldOrder(int $qty = 1): Transaction
    {
        $transaction = $this->createOrder($qty);
        $this->setAge($transaction, Transaction::PAYMENT_WINDOW_MINUTES + 1);

        return $transaction;
    }

    private function setAge(Transaction $transaction, int $minutes): void
    {
        Transaction::query()->whereKey($transaction->id)->toBase()
            ->update(['created_on' => now()->subMinutes($minutes)]);
    }

    private function remoteStatus(Transaction $transaction, string $status, array $extra = []): void
    {
        $this->remote[$transaction->payment->midtrans_order_id] = array_merge([
            'status_code' => '200',
            'transaction_status' => $status,
            'fraud_status' => null,
            'payment_type' => 'gopay',
            'gross_amount' => (string) $transaction->payment->amount,
        ], $extra);
    }

    private function paidNotices(): int
    {
        return count(array_filter($this->roleNotices, fn (string $title) => $title === 'New paid order'));
    }

    private function cancelLogs(): int
    {
        return Stock::where('product_id', $this->product->id)->where('change_type', 'cancel')->count();
    }

    public function test_settled_order_becomes_processing_with_one_paid_notification(): void
    {
        $initialStock = $this->product->fresh()->stock;
        $order = $this->createOldOrder(2);
        $this->remoteStatus($order, 'settlement');

        $this->artisan('orders:expire-pending')->assertExitCode(0);

        $order->refresh();
        $this->assertSame(Transaction::PROCESSING, $order->status);
        $this->assertSame('settlement', $order->payment->payment_status);
        $this->assertNotNull($order->payment->payment_date);
        $this->assertSame(1, $this->paidNotices());
        $this->assertSame($initialStock - 2, $this->product->fresh()->stock);
        $this->assertSame(0, $this->cancelLogs());

        // A second run changes nothing: the order is no longer pending and is not asked about again.
        $this->artisan('orders:expire-pending')->assertExitCode(0);

        $order->refresh();
        $this->assertSame(Transaction::PROCESSING, $order->status);
        $this->assertSame(1, $this->paidNotices());
        $this->assertCount(1, $this->statusChecks);
        $this->assertSame($initialStock - 2, $this->product->fresh()->stock);
    }

    public function test_accepted_capture_is_treated_as_paid(): void
    {
        $order = $this->createOldOrder();
        $this->remoteStatus($order, 'capture', ['fraud_status' => 'accept', 'payment_type' => 'credit_card']);

        $this->artisan('orders:expire-pending')->assertExitCode(0);

        $order->refresh();
        $this->assertSame(Transaction::PROCESSING, $order->status);
        $this->assertSame(1, $this->paidNotices());
    }

    public function test_capture_under_fraud_challenge_is_left_alone(): void
    {
        $initialStock = $this->product->fresh()->stock;
        $order = $this->createOldOrder();
        $this->remoteStatus($order, 'capture', ['fraud_status' => 'challenge', 'payment_type' => 'credit_card']);

        $this->artisan('orders:expire-pending')->assertExitCode(0);

        $order->refresh();
        $this->assertSame(Transaction::PENDING_PAYMENT, $order->status);
        $this->assertSame([], $this->cancelledInvoices);
        $this->assertSame($initialStock - 1, $this->product->fresh()->stock);
        $this->assertSame(0, $this->paidNotices());
    }

    public function test_failed_statuses_are_applied_and_stock_is_returned_once(): void
    {
        $initialStock = $this->product->fresh()->stock;
        $statuses = ['deny', 'cancel', 'expire', 'failure'];

        $orders = [];
        foreach ($statuses as $status) {
            $orders[$status] = $this->createOldOrder();
            $this->remoteStatus($orders[$status], $status);
        }

        $this->artisan('orders:expire-pending')->assertExitCode(0);
        $this->artisan('orders:expire-pending')->assertExitCode(0);

        foreach ($orders as $status => $order) {
            $order->refresh();
            $this->assertSame(Transaction::CANCELLED, $order->status, "Midtrans status '{$status}' should cancel the order");
            $this->assertSame($status, $order->payment->payment_status);
        }

        $this->assertSame($initialStock, $this->product->fresh()->stock);
        $this->assertSame(count($statuses), $this->cancelLogs());
        $this->assertSame(0, $this->paidNotices());
    }

    public function test_pending_remote_status_cancels_order_and_returns_stock_once(): void
    {
        $initialStock = $this->product->fresh()->stock;
        $order = $this->createOldOrder(2);
        $this->remoteStatus($order, 'pending');

        $this->artisan('orders:expire-pending')->assertExitCode(0);
        $this->artisan('orders:expire-pending')->assertExitCode(0);

        $order->refresh();
        $this->assertSame(Transaction::CANCELLED, $order->status);
        $this->assertSame('cancel', $order->payment->payment_status);
        $this->assertSame([$order->payment->midtrans_order_id], $this->cancelledInvoices);
        $this->assertSame($initialStock, $this->product->fresh()->stock);
        $this->assertSame(1, $this->cancelLogs());
    }

    public function test_order_without_midtrans_record_is_cancelled(): void
    {
        $initialStock = $this->product->fresh()->stock;

        $unknown = $this->createOldOrder();   // getStatus() returns null (HTTP 404)
        $notFound = $this->createOldOrder();  // 200 response with status_code 404 in the body
        $this->remote[$notFound->payment->midtrans_order_id] = [
            'status_code' => '404',
            'status_message' => "Transaction doesn't exist.",
        ];

        $this->artisan('orders:expire-pending')->assertExitCode(0);

        $this->assertSame(Transaction::CANCELLED, $unknown->refresh()->status);
        $this->assertSame(Transaction::CANCELLED, $notFound->refresh()->status);
        $this->assertSame($initialStock, $this->product->fresh()->stock);
        $this->assertSame(2, $this->cancelLogs());
    }

    public function test_api_error_on_one_order_does_not_stop_the_rest(): void
    {
        $initialStock = $this->product->fresh()->stock;

        $broken = $this->createOldOrder();
        $paid = $this->createOldOrder();
        $unpaid = $this->createOldOrder();

        $this->remote[$broken->payment->midtrans_order_id] = new DomainException('Midtrans is not reachable right now. Please try again.');
        $this->remoteStatus($paid, 'settlement');
        $this->remoteStatus($unpaid, 'pending');

        Log::spy();

        $this->artisan('orders:expire-pending')
            ->expectsOutputToContain('expired 1, synced 1, failed 1')
            ->assertExitCode(0);

        Log::shouldHaveReceived('info')
            ->withArgs(fn ($message) => str_contains((string) $message, 'expired 1, synced 1, failed 1'))
            ->once();

        // The broken order stays pending and keeps its stock reserved, so the next run retries it.
        $this->assertSame(Transaction::PENDING_PAYMENT, $broken->refresh()->status);
        $this->assertSame(Transaction::PROCESSING, $paid->refresh()->status);
        $this->assertSame(Transaction::CANCELLED, $unpaid->refresh()->status);
        $this->assertSame($initialStock - 2, $this->product->fresh()->stock);
        $this->assertSame(1, $this->paidNotices());
    }

    public function test_pos_and_recent_orders_are_untouched(): void
    {
        $initialStock = $this->product->fresh()->stock;

        $recent = $this->createOrder();
        $this->remoteStatus($recent, 'settlement');

        $almost = $this->createOrder();
        $this->setAge($almost, Transaction::PAYMENT_WINDOW_MINUTES - 1);
        $this->remoteStatus($almost, 'settlement');

        $pos = $this->createOldOrder();
        Transaction::query()->whereKey($pos->id)->toBase()->update(['transaction_type' => Transaction::TYPE_POS]);
        $this->remoteStatus($pos, 'settlement');

        $this->artisan('orders:expire-pending')->assertExitCode(0);

        foreach ([$recent, $almost, $pos] as $order) {
            $this->assertSame(Transaction::PENDING_PAYMENT, $order->refresh()->status);
        }

        $this->assertSame([], $this->statusChecks);
        $this->assertSame(0, $this->paidNotices());
        $this->assertSame($initialStock - 3, $this->product->fresh()->stock);
    }
}