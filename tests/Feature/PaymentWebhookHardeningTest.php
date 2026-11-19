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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class PaymentWebhookHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/midtrans/notification';

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
            'product_name' => 'Tulip Bundle',
            'price' => 100000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);
    }

    private function createPendingOrder(): Transaction
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test');
            $mock->shouldReceive('cancelInvoice')->andReturnNull();
        });

        return app(TransactionService::class)->createOnlineTransaction(
            [['product_id' => $this->product->id, 'qty' => 2]],
            ['name' => 'Member', 'phone' => '081234567890', 'email' => 'member@example.test'],
            ['method' => 'pickup'],
            $this->member->id,
        );
    }

    private function payload(Transaction $transaction, array $override = []): array
    {
        return array_merge([
            'order_id' => $transaction->payment->midtrans_order_id,
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
            'payment_type' => 'gopay',
            'gross_amount' => (string) $transaction->payment->amount,
        ], $override);
    }

    private function fakeNotification(array $payload): void
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) use ($payload) {
            $mock->shouldReceive('readNotification')->andReturn($payload);
            $mock->shouldReceive('cancelInvoice')->andReturnNull();
        });
    }

    private function assertStillPending(Transaction $transaction): void
    {
        $transaction->refresh();

        $this->assertSame(Transaction::PENDING_PAYMENT, $transaction->status);
        $this->assertSame('pending', $transaction->payment->payment_status);
        $this->assertNull($transaction->payment->payment_date);
    }

    public function test_settlement_and_capture_without_amount_are_rejected(): void
    {
        $transaction = $this->createPendingOrder();

        foreach (['settlement', 'capture'] as $status) {
            $payload = $this->payload($transaction, ['transaction_status' => $status]);
            unset($payload['gross_amount']);

            $this->fakeNotification($payload);

            $this->postJson(self::URL, [])->assertStatus(422);
            $this->assertStillPending($transaction);
        }
    }

    public function test_expire_without_amount_is_still_accepted(): void
    {
        $transaction = $this->createPendingOrder();

        $payload = $this->payload($transaction, ['transaction_status' => 'expire']);
        unset($payload['gross_amount']);

        $this->fakeNotification($payload);

        $this->postJson(self::URL, [])->assertOk();

        $this->assertSame(Transaction::CANCELLED, $transaction->refresh()->status);
    }

    public function test_unknown_order_is_answered_with_404_and_logged(): void
    {
        $transaction = $this->createPendingOrder();

        $this->fakeNotification($this->payload($transaction, ['order_id' => 'FS-DOES-NOT-EXIST']));

        Log::spy();

        $this->postJson(self::URL, [])->assertStatus(404);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => str_contains($message, 'unknown order'))
            ->once();

        $this->assertStillPending($transaction);
    }

    public function test_malformed_or_incomplete_body_is_a_400_json_error(): void
    {
        $this->app->instance(MidtransService::class, new MidtransService());
        config(['services.midtrans.server_key' => 'test-server-key']);

        $this->call('POST', self::URL, [], [], [], ['CONTENT_TYPE' => 'application/json'], 'this is not json')
            ->assertStatus(400)
            ->assertJson(['message' => 'Malformed notification']);

        $full = [
            'order_id' => 'FS-1-ABC',
            'status_code' => '200',
            'gross_amount' => '200000.00',
            'transaction_status' => 'settlement',
            'signature_key' => 'whatever',
        ];

        foreach (array_keys($full) as $missing) {
            $body = $full;
            unset($body[$missing]);

            $this->postJson(self::URL, $body)->assertStatus(400);
        }
    }

    public function test_correctly_signed_body_without_transaction_id_is_a_400_not_a_500(): void
    {
        $this->app->instance(MidtransService::class, new MidtransService());
        config(['services.midtrans.server_key' => 'test-server-key']);

        $orderId = 'FS-1-ABC';
        $amount = '200000.00';

        $this->postJson(self::URL, [
            'order_id' => $orderId,
            'status_code' => '200',
            'gross_amount' => $amount,
            'transaction_status' => 'settlement',
            'signature_key' => hash('sha512', $orderId.'200'.$amount.'test-server-key'),
        ])->assertStatus(400);
    }

    public function test_midtrans_or_network_error_is_a_503_so_midtrans_retries(): void
    {
        $transaction = $this->createPendingOrder();

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('readNotification')->andThrow(new RuntimeException('Connection timed out'));
        });

        $this->postJson(self::URL, [])->assertStatus(503);

        $this->assertStillPending($transaction);
    }

    public function test_malformed_notification_from_the_service_is_a_400(): void
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('readNotification')->andThrow(new InvalidArgumentException('missing order_id'));
        });

        $this->postJson(self::URL, [])->assertStatus(400);
    }

    public function test_amount_is_checked_against_the_payment_row_and_the_midtrans_response(): void
    {
        $transaction = $this->createPendingOrder();
        $amount = (string) $transaction->payment->amount;

        // The signed body says a different amount than the order.
        $this->fakeNotification($this->payload($transaction, ['notified_gross_amount' => '1']));
        $this->postJson(self::URL, [])->assertStatus(422);
        $this->assertStillPending($transaction);

        // Midtrans answers a different amount than the order.
        $this->fakeNotification($this->payload($transaction, ['gross_amount' => '1', 'notified_gross_amount' => $amount]));
        $this->postJson(self::URL, [])->assertStatus(422);
        $this->assertStillPending($transaction);

        // Both agree with the order.
        $this->fakeNotification($this->payload($transaction, ['notified_gross_amount' => $amount]));
        $this->postJson(self::URL, [])->assertOk();

        $this->assertSame(Transaction::PROCESSING, $transaction->refresh()->status);
    }

    public function test_unknown_transaction_status_is_logged_and_changes_nothing(): void
    {
        $transaction = $this->createPendingOrder();

        $this->fakeNotification($this->payload($transaction, ['transaction_status' => 'some_new_status']));

        Log::spy();

        $this->postJson(self::URL, [])->assertOk();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => str_contains($message, 'unknown transaction_status'))
            ->once();

        $this->assertStillPending($transaction);
    }

    public function test_same_settlement_twice_changes_nothing_and_sends_one_new_paid_order_notification(): void
    {
        $transaction = $this->createPendingOrder();

        $this->fakeNotification($this->payload($transaction));

        $this->postJson(self::URL, [])->assertOk();
        $paidAt = $transaction->payment->refresh()->payment_date;

        $this->postJson(self::URL, [])->assertOk();

        $transaction->refresh();
        $this->assertSame(Transaction::PROCESSING, $transaction->status);
        $this->assertEquals($paidAt, $transaction->payment->refresh()->payment_date);

        $perUser = DB::table('notifications')
            ->where('title', 'New paid order')
            ->select('user_id', DB::raw('count(*) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $this->assertNotEmpty($perUser);

        foreach ($perUser as $total) {
            $this->assertSame(1, (int) $total);
        }
    }

    public function test_webhook_route_has_a_generous_per_ip_throttle(): void
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('readNotification')->andThrow(new InvalidArgumentException('x'));
        });

        for ($i = 0; $i < 300; $i++) {
            $this->assertSame(400, $this->postJson(self::URL, [])->getStatusCode());
        }

        $this->postJson(self::URL, [])->assertStatus(429);
    }
}
