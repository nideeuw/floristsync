<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Database\Seeders\RolesMenusSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class OrderReceivedConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);
        config(['floristsync.auto_complete_shipped_days' => 3]);
    }

    private function user(string $roleName, string $email): User
    {
        $user = new User();
        $user->forceFill([
            'name' => ucfirst($roleName).' Test',
            'email' => $email,
            'password' => Hash::make('Flower2026'),
            'role_id' => Role::where('role_name', $roleName)->firstOrFail()->id,
            'is_active' => true,
        ])->save();

        return $user;
    }

    private function order(User $owner, array $attributes = []): Transaction
    {
        return Transaction::create(array_merge([
            'user_id' => $owner->id,
            'transaction_type' => Transaction::TYPE_ONLINE,
            'status' => Transaction::SHIPPED,
            'courier' => 'jne',
            'shipping_cost' => 10000,
            'total' => 100000,
            'shipped_at' => now(),
        ], $attributes));
    }

    public function test_the_owner_confirms_a_shipped_order(): void
    {
        $member = $this->user(Role::MEMBER, 'owner@example.test');
        $order = $this->order($member);

        $this->actingAs($member)
            ->post(route('track.received', $order))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(Transaction::COMPLETED, $order->fresh()->status);
    }

    public function test_another_member_gets_404(): void
    {
        $owner = $this->user(Role::MEMBER, 'owner@example.test');
        $other = $this->user(Role::MEMBER, 'other@example.test');
        $order = $this->order($owner);

        $this->actingAs($other)
            ->post(route('track.received', $order))
            ->assertNotFound();

        $this->assertSame(Transaction::SHIPPED, $order->fresh()->status);
    }

    public function test_a_processing_order_is_refused(): void
    {
        $member = $this->user(Role::MEMBER, 'owner@example.test');
        $order = $this->order($member, ['status' => Transaction::PROCESSING, 'shipped_at' => null]);

        $this->actingAs($member)
            ->post(route('track.received', $order))
            ->assertSessionHas('error');

        $this->assertSame(Transaction::PROCESSING, $order->fresh()->status);
    }

    public function test_a_pickup_order_is_refused(): void
    {
        $member = $this->user(Role::MEMBER, 'owner@example.test');
        $order = $this->order($member, [
            'status' => Transaction::READY_FOR_PICKUP,
            'courier' => null,
            'shipping_cost' => null,
            'shipped_at' => null,
        ]);

        $this->actingAs($member)
            ->post(route('track.received', $order))
            ->assertSessionHas('error');

        $this->assertSame(Transaction::READY_FOR_PICKUP, $order->fresh()->status);

        // Even a pickup row that somehow has the shipped status is refused.
        $odd = $this->order($member, ['courier' => null, 'shipping_cost' => null]);

        $this->expectException(DomainException::class);
        app(TransactionService::class)->confirmReceived($odd, $member);
    }

    public function test_the_tracking_number_sets_shipped_at(): void
    {
        $member = $this->user(Role::MEMBER, 'owner@example.test');
        $order = $this->order($member, ['status' => Transaction::PROCESSING, 'shipped_at' => null]);

        app(TransactionService::class)->inputTrackingNumber($order, 'JNE123456');

        $order->refresh();
        $this->assertSame(Transaction::SHIPPED, $order->status);
        $this->assertNotNull($order->shipped_at);
    }

    public function test_the_command_completes_only_shipped_orders_older_than_the_configured_days(): void
    {
        $member = $this->user(Role::MEMBER, 'owner@example.test');

        $old = $this->order($member, ['shipped_at' => now()->subDays(4)]);
        $recent = $this->order($member, ['shipped_at' => now()->subDay()]);
        $unknown = $this->order($member, ['shipped_at' => null]);
        $processing = $this->order($member, ['status' => Transaction::PROCESSING, 'shipped_at' => now()->subDays(10)]);
        $pickup = $this->order($member, [
            'status' => Transaction::READY_FOR_PICKUP,
            'courier' => null,
            'shipping_cost' => null,
            'shipped_at' => now()->subDays(10),
        ]);

        $this->artisan('orders:auto-complete-shipped')->assertExitCode(0);

        $this->assertSame(Transaction::COMPLETED, $old->fresh()->status);
        $this->assertSame(Transaction::SHIPPED, $recent->fresh()->status);
        $this->assertSame(Transaction::SHIPPED, $unknown->fresh()->status);
        $this->assertSame(Transaction::PROCESSING, $processing->fresh()->status);
        $this->assertSame(Transaction::READY_FOR_PICKUP, $pickup->fresh()->status);
    }

    public function test_a_failure_on_one_order_does_not_stop_the_rest(): void
    {
        $member = $this->user(Role::MEMBER, 'owner@example.test');

        $bad = $this->order($member, ['shipped_at' => now()->subDays(5)]);
        $good = $this->order($member, ['shipped_at' => now()->subDays(5)]);

        $real = app(TransactionService::class);

        $this->partialMock(TransactionService::class, function ($mock) use ($bad, $real) {
            $mock->shouldReceive('changeStatus')->andReturnUsing(
                function (Transaction $transaction, string $status) use ($bad, $real) {
                    if ($transaction->id === $bad->id) {
                        throw new RuntimeException('boom');
                    }

                    return $real->changeStatus($transaction, $status);
                }
            );
        });

        $this->artisan('orders:auto-complete-shipped')->assertExitCode(0);

        $this->assertSame(Transaction::SHIPPED, $bad->fresh()->status);
        $this->assertSame(Transaction::COMPLETED, $good->fresh()->status);
    }

    public function test_the_admin_can_still_complete_a_shipped_order_by_hand(): void
    {
        $member = $this->user(Role::MEMBER, 'owner@example.test');
        $order = $this->order($member);
        $service = app(TransactionService::class);

        $this->assertContains(Transaction::COMPLETED, $service->allowedNextStatuses($order));

        $service->changeStatus($order, Transaction::COMPLETED);

        $this->assertSame(Transaction::COMPLETED, $order->fresh()->status);
    }
}
