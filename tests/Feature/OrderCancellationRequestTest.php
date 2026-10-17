<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MidtransService;
use App\Services\OrderCancellationService;
use App\Services\TransactionService;
use Database\Seeders\RolesMenusSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class OrderCancellationRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $otherMember;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $memberRole = Role::where('role_name', Role::MEMBER)->value('id');

        $this->member = User::factory()->create([
            'role_id' => $memberRole,
            'is_active' => true,
        ]);

        $this->otherMember = User::factory()->create([
            'role_id' => $memberRole,
            'is_active' => true,
        ]);

        $category = Category::factory()->create();

        $this->product = Product::factory()->create([
            'category_id' => $category->id,
            'product_name' => 'Lily Arrangement',
            'price' => 150000,
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

    private function createOrder(string $status, ?User $owner = null, int $qty = 2): Transaction
    {
        $owner ??= $this->member;

        $transaction = app(TransactionService::class)->createOnlineTransaction(
            [['product_id' => $this->product->id, 'qty' => $qty]],
            ['name' => 'Member', 'phone' => '081234567890', 'email' => 'member@example.test'],
            ['method' => 'pickup'],
            $owner->id,
        );

        if ($status !== Transaction::PENDING_PAYMENT) {
            $transaction->update(['status' => $status]);
        }

        return $transaction->refresh();
    }

    public function test_pending_payment_order_is_cancelled_immediately(): void
    {
        $initialStock = $this->product->fresh()->stock;
        $transaction = $this->createOrder(Transaction::PENDING_PAYMENT);

        $result = app(OrderCancellationService::class)
            ->request($transaction, $this->member, 'Changed my mind');

        $this->assertSame(Transaction::CANCELLED, $result->status);
        $this->assertSame($initialStock, $this->product->fresh()->stock);
        $this->assertSame('Changed my mind', $result->cancel_reason);
        $this->assertSame($this->member->id, (int) $result->cancelled_by);
        $this->assertNotNull($result->cancelled_at);
        $this->assertSame('cancel', $result->payment->payment_status);
    }

    public function test_processing_order_creates_review_request(): void
    {
        $transaction = $this->createOrder(Transaction::PROCESSING);

        $result = app(OrderCancellationService::class)
            ->request($transaction, $this->member, 'No longer needed');

        $this->assertSame(Transaction::PROCESSING, $result->status);
        $this->assertSame(Transaction::CANCEL_PENDING, $result->cancel_request_status);
        $this->assertNotNull($result->cancel_requested_at);
        $this->assertSame('No longer needed', $result->cancel_reason);

        $this->assertDatabaseHas('notifications', [
            'title' => 'Cancellation request',
        ]);
    }

    public function test_shipped_order_is_refused(): void
    {
        $transaction = $this->createOrder(Transaction::SHIPPED);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('This order can no longer be cancelled online.');

        app(OrderCancellationService::class)
            ->request($transaction, $this->member, 'Too late to cancel');
    }

    public function test_other_members_order_is_refused(): void
    {
        $transaction = $this->createOrder(Transaction::PROCESSING, $this->member);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('This order does not belong to you.');

        app(OrderCancellationService::class)
            ->request($transaction, $this->otherMember, 'Not my order');
    }

    public function test_processing_request_is_only_created_once(): void
    {
        $transaction = $this->createOrder(Transaction::PROCESSING);
        $service = app(OrderCancellationService::class);

        $service->request($transaction, $this->member, 'First request');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('already waiting for review');

        $service->request($transaction->refresh(), $this->member, 'Second request');
    }
}
