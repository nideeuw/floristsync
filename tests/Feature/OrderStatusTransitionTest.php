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
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $this->member = User::factory()->create([
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'role_id' => Role::where('role_name', Role::ADMIN_ORDER)->value('id'),
            'is_active' => true,
        ]);

        $this->product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'product_name' => 'Rose Bundle',
            'price' => 100000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);
    }

    private function order(string $status, bool $delivery = false, string $type = Transaction::TYPE_ONLINE): Transaction
    {
        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test');
            $mock->shouldReceive('cancelInvoice')->andReturnNull();
        });

        $transaction = app(TransactionService::class)->createOnlineTransaction(
            [['product_id' => $this->product->id, 'qty' => 1]],
            ['name' => 'Member', 'phone' => '081234567890', 'email' => 'member@example.test'],
            ['method' => 'pickup'],
            $this->member->id,
        );

        $attributes = ['status' => $status, 'transaction_type' => $type];

        if ($delivery) {
            $attributes['courier'] = 'jne';
            $attributes['shipping_address'] = "Member (081234567890)\nJl. Mawar 1";
        }

        $transaction->update($attributes);

        return $transaction->refresh();
    }

    public static function allowedTransitions(): array
    {
        return [
            'pickup processing to ready for pickup' => [Transaction::PROCESSING, false, Transaction::READY_FOR_PICKUP],
            'pickup ready for pickup to completed' => [Transaction::READY_FOR_PICKUP, false, Transaction::COMPLETED],
            'delivery shipped to completed' => [Transaction::SHIPPED, true, Transaction::COMPLETED],
        ];
    }

    public static function rejectedTransitions(): array
    {
        return [
            'completed back to processing' => [Transaction::COMPLETED, false, Transaction::PROCESSING],
            'completed back to ready for pickup' => [Transaction::COMPLETED, true, Transaction::READY_FOR_PICKUP],
            'delivery processing skips shipping' => [Transaction::PROCESSING, true, Transaction::COMPLETED],
            'pickup processing skips a step' => [Transaction::PROCESSING, false, Transaction::COMPLETED],
            'ready for pickup back to processing' => [Transaction::READY_FOR_PICKUP, false, Transaction::PROCESSING],
            'shipped back to processing' => [Transaction::SHIPPED, true, Transaction::PROCESSING],
            'pending payment to processing' => [Transaction::PENDING_PAYMENT, false, Transaction::PROCESSING],
            'cancelled to completed' => [Transaction::CANCELLED, false, Transaction::COMPLETED],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function test_api_allows_the_next_step_of_the_flow(string $from, bool $delivery, string $to): void
    {
        $order = $this->order($from, $delivery);

        $this->actingAs($this->admin);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => $to])
            ->assertOk()
            ->assertJsonPath('message', 'Status updated.');

        $this->assertSame($to, $order->refresh()->status);
    }

    #[DataProvider('rejectedTransitions')]
    public function test_api_rejects_a_transition_the_web_flow_does_not_allow(string $from, bool $delivery, string $to): void
    {
        $order = $this->order($from, $delivery);

        $this->actingAs($this->admin);

        $fromLabel = str_replace('_', ' ', $from);
        $toLabel = str_replace('_', ' ', $to);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => $to])
            ->assertStatus(422)
            ->assertJsonPath('message', "This order cannot be moved from {$fromLabel} to {$toLabel}.");

        $this->assertSame($from, $order->refresh()->status);
    }

    public function test_pos_sale_cannot_change_status_through_the_api(): void
    {
        $order = $this->order(Transaction::PROCESSING, false, Transaction::TYPE_POS);

        $this->actingAs($this->admin);

        $this->patchJson("/api/orders/{$order->id}/status", ['status' => Transaction::READY_FOR_PICKUP])
            ->assertStatus(422);

        $this->assertSame(Transaction::PROCESSING, $order->refresh()->status);
    }

    public function test_transition_list_is_shared_through_the_service(): void
    {
        $service = app(TransactionService::class);

        $this->assertSame([Transaction::READY_FOR_PICKUP], $service->allowedNextStatuses($this->order(Transaction::PROCESSING)));
        $this->assertSame([], $service->allowedNextStatuses($this->order(Transaction::PROCESSING, true)));
        $this->assertSame([Transaction::COMPLETED], $service->allowedNextStatuses($this->order(Transaction::SHIPPED, true)));
        $this->assertSame([], $service->allowedNextStatuses($this->order(Transaction::COMPLETED)));
        $this->assertSame([], $service->allowedNextStatuses($this->order(Transaction::PROCESSING, false, Transaction::TYPE_POS)));
    }
}