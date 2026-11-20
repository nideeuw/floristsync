<?php

namespace Tests\Feature;

use App\Jobs\SendInvoiceMail;
use App\Mail\OrderInvoiceMail;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class InvoiceEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $owner;

    private User $cashier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $this->member = $this->userWithRole(Role::MEMBER);
        $this->owner = $this->userWithRole(Role::OWNER);
        $this->cashier = $this->userWithRole(Role::CASHIER);

        $this->product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'product_name' => 'Lily Bundle',
            'price' => 100000,
            'stock' => 30,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $this->mock(MidtransService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test');
            $mock->shouldReceive('cancelInvoice')->andReturnNull();
            $mock->shouldReceive('getStatus')->andReturn(null);
        });
    }

    private function userWithRole(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('role_name', $role)->value('id'),
            'is_active' => true,
        ]);
    }

    private function createOrder(?User $buyer = null): Transaction
    {
        return app(TransactionService::class)->createOnlineTransaction(
            [['product_id' => $this->product->id, 'qty' => 1]],
            ['name' => 'Member', 'phone' => '081234567890', 'email' => 'member@example.test'],
            ['method' => 'pickup'],
            ($buyer ?? $this->member)->id,
        );
    }

    /** Sends the settlement webhook for this order. The mail job runs after the response, as in production. */
    private function payByWebhook(Transaction $transaction): void
    {
        $payload = [
            'order_id' => $transaction->payment->midtrans_order_id,
            'transaction_status' => 'settlement',
            'fraud_status' => null,
            'payment_type' => $transaction->transaction_type === Transaction::TYPE_POS ? 'qris' : 'gopay',
            'gross_amount' => (string) $transaction->payment->amount,
        ];

        $this->mock(MidtransService::class, function (MockInterface $mock) use ($payload) {
            $mock->shouldReceive('createSnapToken')->andReturn('snap-token-test');
            $mock->shouldReceive('cancelInvoice')->andReturnNull();
            $mock->shouldReceive('getStatus')->andReturn(null);
            $mock->shouldReceive('readNotification')->andReturn($payload);
        });

        $this->postJson('/api/midtrans/notification', [])->assertOk();
    }

    public function test_a_paid_webhook_sends_one_invoice_mail(): void
    {
        Mail::fake();
        $transaction = $this->createOrder();

        $this->payByWebhook($transaction);

        Mail::assertSent(OrderInvoiceMail::class, 1);
        Mail::assertSent(OrderInvoiceMail::class, fn (OrderInvoiceMail $mail) => $mail->hasTo($this->member->email));
        $this->assertNotNull($transaction->fresh()->invoice_emailed_at);
        $this->assertSame(Transaction::PROCESSING, $transaction->fresh()->status);
    }

    public function test_the_same_webhook_twice_still_sends_one_mail(): void
    {
        Mail::fake();
        $transaction = $this->createOrder();

        $this->payByWebhook($transaction);
        $this->payByWebhook($transaction);

        // The Pay now button and the expire command end in the same job, so a direct call must stop as well.
        SendInvoiceMail::dispatchSync($transaction->id);

        Mail::assertSent(OrderInvoiceMail::class, 1);
    }

    public function test_a_pos_sale_and_staff_buyers_get_no_mail(): void
    {
        Mail::fake();

        $pos = app(TransactionService::class)->createPosTransaction(
            [['product_id' => $this->product->id, 'qty' => 1]],
            $this->cashier->id,
            'qris',
        );

        $this->payByWebhook($pos);
        $this->assertSame(Transaction::COMPLETED, $pos->fresh()->status);

        SendInvoiceMail::dispatchSync($pos->id);

        $ownerOrder = $this->createOrder($this->owner);
        $this->payByWebhook($ownerOrder);

        Mail::assertNothingSent();
    }

    public function test_the_profile_toggle_turns_the_mail_off(): void
    {
        Mail::fake();
        $this->member->forceFill(['email_invoice' => false])->save();
        $transaction = $this->createOrder();

        $this->payByWebhook($transaction);

        Mail::assertNothingSent();
        $this->assertNull($transaction->fresh()->invoice_emailed_at);
    }

    public function test_a_mail_failure_does_not_break_the_payment(): void
    {
        $transaction = $this->createOrder();

        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Gmail relay failed: quota exceeded'));

        $this->payByWebhook($transaction);

        $transaction->refresh();
        $this->assertSame(Transaction::PROCESSING, $transaction->status);
        $this->assertNotNull($transaction->payment->payment_date);
        // Not marked as sent, so the member can still use "Resend invoice".
        $this->assertNull($transaction->invoice_emailed_at);
    }

    public function test_resend_is_limited_to_three_a_day_for_each_order(): void
    {
        Mail::fake();
        $transaction = $this->createOrder();
        DB::table('transactions')->where('id', $transaction->id)->update(['status' => Transaction::PROCESSING]);

        $this->actingAs($this->member);

        foreach (range(1, 3) as $i) {
            $this->post(route('track.invoice.resend', $transaction->order_number))->assertSessionHas('success');
        }

        $this->post(route('track.invoice.resend', $transaction->order_number))->assertSessionHas('error');

        $this->assertSame(0, RateLimiter::remaining('invoice-resend:'.$transaction->id, 3));
        Mail::assertSent(OrderInvoiceMail::class);
    }

    public function test_a_resend_over_the_limit_sends_nothing(): void
    {
        Mail::fake();
        $transaction = $this->createOrder();
        DB::table('transactions')->where('id', $transaction->id)->update(['status' => Transaction::PROCESSING]);

        // Use up the 3 allowed resends directly, so only ONE request is made below.
        // Several requests in one test run the after-response callbacks of the earlier ones again,
        // which makes mail counts unreliable. A real request starts a fresh app, so this is test-only.
        foreach (range(1, 3) as $i) {
            RateLimiter::hit('invoice-resend:'.$transaction->id, 86400);
        }

        $this->actingAs($this->member)
            ->post(route('track.invoice.resend', $transaction->order_number))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_resend_is_for_the_owner_of_a_paid_order_only(): void
    {
        Mail::fake();
        $paid = $this->createOrder();
        DB::table('transactions')->where('id', $paid->id)->update(['status' => Transaction::PROCESSING]);
        $unpaid = $this->createOrder();

        $this->actingAs($this->userWithRole(Role::MEMBER))
            ->post(route('track.invoice.resend', $paid->order_number))
            ->assertNotFound();

        $this->actingAs($this->member)
            ->post(route('track.invoice.resend', $unpaid->order_number))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_the_profile_form_saves_the_toggle(): void
    {
        $this->actingAs($this->member)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Email me my invoice');

        $this->actingAs($this->member)->patch(route('profile.update'), [
            'name' => $this->member->name,
            'email' => $this->member->email,
            'email_invoice' => '0',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($this->member->fresh()->email_invoice);

        $this->actingAs($this->member)->patch(route('profile.update'), [
            'name' => $this->member->name,
            'email' => $this->member->email,
            'email_invoice' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($this->member->fresh()->email_invoice);
    }

    public function test_the_mail_contains_the_invoice(): void
    {
        $transaction = $this->createOrder();
        DB::table('transactions')->where('id', $transaction->id)->update(['status' => Transaction::PROCESSING]);

        $html = (new OrderInvoiceMail($transaction->fresh()))->render();

        $this->assertStringContainsString($transaction->order_number, $html);
        $this->assertStringContainsString('Lily Bundle', $html);
        $this->assertStringContainsString('View invoice', $html);
        $this->assertStringContainsString(route('track.invoice', $transaction->order_number), $html);
    }
}