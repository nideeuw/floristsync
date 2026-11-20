<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Menu;
use App\Models\MenuPermission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AuditService;
use App\Services\MidtransService;
use App\Services\OrderCancellationService;
use App\Services\StaffService;
use App\Services\TransactionService;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery\MockInterface;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $admin;

    private User $inventory;

    private User $owner;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $this->member = $this->userWithRole(Role::MEMBER);
        $this->admin = $this->userWithRole(Role::ADMIN_ORDER);
        $this->inventory = $this->userWithRole(Role::ADMIN_INVENTORY);
        $this->owner = $this->userWithRole(Role::OWNER);

        $this->product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'product_name' => 'Lily Bundle',
            'price' => 100000,
            'stock' => 20,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test');
            $mock->shouldReceive('cancelInvoice')->andReturnNull();
            $mock->shouldReceive('getStatus')->andReturn(['transaction_status' => 'pending']);
            $mock->shouldReceive('cancelTransaction')->andReturnNull();
        });
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('role_name', $role)->value('id'),
            'is_active' => true,
        ]);
    }

    private function createOrder(): Transaction
    {
        return app(TransactionService::class)->createOnlineTransaction(
            [['product_id' => $this->product->id, 'qty' => 1]],
            ['name' => 'Member', 'phone' => '081234567890', 'email' => 'member@example.test'],
            ['method' => 'pickup'],
            $this->member->id,
        );
    }

    private function statusLog(Transaction $transaction): AuditLog
    {
        return AuditLog::where('action', 'order.status_changed')
            ->where('entity_id', $transaction->id)
            ->latest('id')
            ->firstOrFail();
    }

    private function settleByWebhook(Transaction $transaction): void
    {
        $payload = [
            'order_id' => $transaction->payment->midtrans_order_id,
            'transaction_status' => 'settlement',
            'fraud_status' => null,
            'payment_type' => 'gopay',
            'gross_amount' => (string) $transaction->payment->amount,
        ];

        $this->mock(MidtransService::class, fn (MockInterface $m) => $m->shouldReceive('readNotification')->andReturn($payload));

        $this->postJson('/api/midtrans/notification', [])->assertOk();
    }

    public function test_webhook_settlement_writes_a_status_row(): void
    {
        $transaction = $this->createOrder();

        $this->settleByWebhook($transaction);

        $log = $this->statusLog($transaction);
        $this->assertSame('webhook', $log->source);
        $this->assertNull($log->user_id);
        $this->assertSame('transaction', $log->entity);
        $this->assertSame(['status' => Transaction::PENDING_PAYMENT], $log->old_values);
        $this->assertSame(['status' => Transaction::PROCESSING], $log->new_values);
    }

    public function test_admin_status_change_writes_a_row_with_the_admin(): void
    {
        $transaction = $this->createOrder();
        DB::table('transactions')->where('id', $transaction->id)->update(['status' => Transaction::PROCESSING]);

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.status', $transaction), ['status' => 'ready_for_pickup'])
            ->assertSessionHasNoErrors();

        $log = $this->statusLog($transaction);
        $this->assertSame('admin', $log->source);
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame(['status' => Transaction::PROCESSING], $log->old_values);
        $this->assertSame(['status' => Transaction::READY_FOR_PICKUP], $log->new_values);
    }

    public function test_member_cancel_writes_a_row_with_the_member(): void
    {
        $transaction = $this->createOrder();

        $this->actingAs($this->member);
        app(OrderCancellationService::class)->request($transaction, $this->member, 'Changed my mind');

        $log = $this->statusLog($transaction);
        $this->assertSame('member', $log->source);
        $this->assertSame($this->member->id, $log->user_id);
        $this->assertSame(Transaction::CANCELLED, $log->new_values['status']);
    }

    public function test_expire_command_writes_a_scheduler_row(): void
    {
        $transaction = $this->createOrder();
        DB::table('transactions')->where('id', $transaction->id)->update(['created_on' => now()->subHours(2)]);

        Artisan::call('orders:expire-pending');

        $log = $this->statusLog($transaction);
        $this->assertSame('scheduler', $log->source);
        $this->assertNull($log->user_id);
        $this->assertSame(Transaction::PENDING_PAYMENT, $log->old_values['status']);
        $this->assertSame(Transaction::CANCELLED, $log->new_values['status']);
    }

    public function test_passwords_and_tokens_never_appear_in_the_log(): void
    {
        $this->actingAs($this->owner);
        $roleId = Role::where('role_name', Role::CASHIER)->value('id');

        $staff = app(StaffService::class)->create([
            'role_id' => $roleId,
            'name' => 'New Cashier',
            'email' => 'new.cashier@example.test',
            'phone' => '081200000099',
            'password' => 'Secret-Passw0rd-123',
        ]);

        app(StaffService::class)->update($staff, [
            'role_id' => $roleId,
            'name' => 'Renamed Cashier',
            'email' => 'new.cashier@example.test',
            'phone' => '081200000099',
            'password' => 'Another-Passw0rd-456',
        ]);

        app(AuditService::class)->log(
            'test.sensitive',
            'user',
            $staff->id,
            ['password' => 'old-secret-value'],
            ['password' => 'Plain-Secret-1', 'api_token' => 'tok_123', 'nested' => ['secret' => 'hush', 'safe' => 'visible']],
        );

        $dump = json_encode(AuditLog::all()->toArray());

        foreach (['Secret-Passw0rd-123', 'Another-Passw0rd-456', 'old-secret-value', 'Plain-Secret-1', 'tok_123', 'hush'] as $secret) {
            $this->assertStringNotContainsString($secret, $dump);
        }

        $this->assertStringContainsString('visible', $dump);
        $this->assertDatabaseHas('audit_logs', ['action' => 'staff.created', 'entity_id' => $staff->id, 'user_id' => $this->owner->id]);

        $updated = AuditLog::where('action', 'staff.updated')->firstOrFail();
        $this->assertSame('Renamed Cashier', $updated->new_values['name']);
        $this->assertTrue($updated->new_values['credentials_changed']);
    }

    public function test_product_toggle_and_stock_adjustment_are_logged(): void
    {
        $this->actingAs($this->inventory)
            ->patch(route('admin.products.toggle', $this->product))
            ->assertSessionHasNoErrors();

        $toggle = AuditLog::where('action', 'product.updated')->firstOrFail();
        $this->assertSame($this->inventory->id, $toggle->user_id);
        $this->assertSame('admin', $toggle->source);
        $this->assertSame(['is_active' => true], $toggle->old_values);
        $this->assertSame(['is_active' => false], $toggle->new_values);

        DB::table('products')->where('id', $this->product->id)->update(['is_active' => true]);

        $this->actingAs($this->inventory)
            ->post(route('admin.stock.adjust.store'), ['product_id' => $this->product->id, 'change' => 5, 'notes' => 'Recount'])
            ->assertSessionHasNoErrors();

        $stock = AuditLog::where('action', 'stock.adjusted')->firstOrFail();
        $this->assertSame($this->product->id, $stock->entity_id);
        $this->assertSame(5, $stock->new_values['change']);
    }

    public function test_cancellation_reject_is_logged(): void
    {
        $transaction = $this->createOrder();
        DB::table('transactions')->where('id', $transaction->id)->update([
            'status' => Transaction::PROCESSING,
            'cancel_request_status' => Transaction::CANCEL_PENDING,
            'cancel_requested_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.orders.cancellations.reject', $transaction))
            ->assertSessionHasNoErrors();

        $log = AuditLog::where('action', 'cancellation.rejected')->firstOrFail();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame($transaction->id, $log->entity_id);
        $this->assertSame(Transaction::CANCEL_REJECTED, $log->new_values['cancel_request_status']);
    }

    public function test_staff_login_is_logged(): void
    {
        $owner = User::where('email', 'owner@floristsync.test')->firstOrFail();

        $this->post(route('admin.login.store'), ['email' => $owner->email, 'password' => 'password']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login',
            'user_id' => $owner->id,
            'source' => 'admin',
        ]);
    }

    public function test_only_the_owner_can_open_the_list_and_filters_work(): void
    {
        app(AuditService::class)->log('staff.created', 'user', 1, null, ['name' => 'A'], userId: $this->admin->id);
        app(AuditService::class)->log('product.updated', 'product', 2, ['price' => 1], ['price' => 2], userId: $this->inventory->id);

        $this->actingAs($this->admin)->get(route('admin.audit-logs'))->assertForbidden();
        $this->actingAs($this->inventory)->get(route('admin.audit-logs'))->assertForbidden();

        $this->actingAs($this->owner)->get(route('admin.audit-logs'))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->count() === 2);

        $this->actingAs($this->owner)->get(route('admin.audit-logs', ['action' => 'product.updated']))
            ->assertViewHas('logs', fn ($logs) => $logs->count() === 1 && $logs->first()->action === 'product.updated');

        $this->actingAs($this->owner)->get(route('admin.audit-logs', ['user_id' => $this->admin->id]))
            ->assertViewHas('logs', fn ($logs) => $logs->count() === 1 && $logs->first()->action === 'staff.created');

        $this->actingAs($this->owner)->get(route('admin.audit-logs', ['from' => now()->addDay()->toDateString()]))
            ->assertViewHas('logs', fn ($logs) => $logs->count() === 0);
    }

    public function test_status_timeline_is_shown_on_both_detail_pages(): void
    {
        $transaction = $this->createOrder();
        $this->settleByWebhook($transaction);

        $this->actingAs($this->admin)->get(route('admin.orders.show', $transaction))
            ->assertOk()
            ->assertSee('Status history')
            ->assertSee('Pending payment')
            ->assertSee('webhook');

        $this->actingAs($this->owner)->get(route('admin.transactions.show', $transaction))
            ->assertOk()
            ->assertSee('Status history')
            ->assertSee('Processing');
    }

    public function test_audit_menu_is_owner_only(): void
    {
        $menu = Menu::where('url_link', 'admin.audit-logs')->firstOrFail();

        $roles = MenuPermission::where('menu_id', $menu->id)->with('role')->get()->pluck('role.role_name')->all();

        $this->assertSame([Role::OWNER], $roles);
    }
}
