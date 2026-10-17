<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private User $otherMember;

    private User $cashier;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $memberRole = Role::where('role_name', Role::MEMBER)->firstOrFail();
        $cashierRole = Role::where('role_name', Role::CASHIER)->firstOrFail();

        $this->member = User::factory()->create([
            'name' => 'John Member',
            'email' => 'john@example.com',
            'phone' => '08123456789',
            'role_id' => $memberRole->id,
            'is_active' => true,
        ]);

        $this->otherMember = User::factory()->create([
            'name' => 'Jane Other',
            'email' => 'jane@example.com',
            'phone' => '08987654321',
            'role_id' => $memberRole->id,
            'is_active' => true,
        ]);

        $this->cashier = User::factory()->create([
            'name' => 'Sarah Cashier',
            'role_id' => $cashierRole->id,
            'is_active' => true,
        ]);

        $category = Category::create([
            'category_name' => 'Bouquets',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'product_name' => 'Romantic Rose Bouquet',
            'price' => 150000,
            'stock' => 10,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        Setting::updateOrCreate(
            ['setting_key' => 'store_name'],
            ['setting_value' => 'FloristSync Premium Store', 'is_active' => true]
        );

        Setting::updateOrCreate(
            ['setting_key' => 'address'],
            ['setting_value' => 'Jl. Mawar Indah No. 42, Malang', 'is_active' => true]
        );

        Setting::updateOrCreate(
            ['setting_key' => 'phone'],
            ['setting_value' => '0341-555987', 'is_active' => true]
        );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $transaction = $this->createOnlineOrder($this->member, Transaction::COMPLETED, 'settlement');

        $response = $this->get(route('track.invoice', $transaction));

        $response->assertRedirect(route('login'));
    }

    public function test_non_member_cannot_access_member_invoice(): void
    {
        $transaction = $this->createOnlineOrder($this->member, Transaction::COMPLETED, 'settlement');

        $response = $this->actingAs($this->cashier)->get(route('track.invoice', $transaction));

        $response->assertForbidden();
    }

    public function test_member_can_view_invoice_for_own_completed_delivery_order(): void
    {
        $transaction = $this->createOnlineOrder(
            user: $this->member,
            status: Transaction::COMPLETED,
            paymentStatus: 'settlement',
            isDelivery: true,
            courier: 'jne',
            shippingCost: 20000,
            trackingNumber: 'JNE987654321',
            notes: 'Please add a red ribbon'
        );

        $response = $this->actingAs($this->member)->get(route('track.invoice', $transaction));

        $response->assertOk();
        $response->assertSee('FloristSync Premium Store');
        $response->assertSee('Jl. Mawar Indah No. 42, Malang');
        $response->assertSee('0341-555987');
        $response->assertSee($transaction->order_number);
        $response->assertSee('Romantic Rose Bouquet');
        $response->assertSee(rupiah(150000));
        $response->assertSee(rupiah(20000)); // Shipping cost
        $response->assertSee(rupiah(170000)); // Grand total
        $response->assertSee('MIDTRANS');
        $response->assertSee('Settlement');
        $response->assertSee('Delivery');
        $response->assertSee('JNE');
        $response->assertSee('JNE987654321');
        $response->assertSee('Please add a red ribbon');
        $response->assertSee('Print Invoice');
    }

    public function test_member_can_view_invoice_for_own_pickup_order(): void
    {
        $transaction = $this->createOnlineOrder(
            user: $this->member,
            status: Transaction::PROCESSING,
            paymentStatus: 'settlement',
            isDelivery: false
        );

        $response = $this->actingAs($this->member)->get(route('track.invoice', $transaction));

        $response->assertOk();
        $response->assertSee('Store Pickup');
        $response->assertSee('Free (Pickup)');
        $response->assertSee(rupiah(150000));
        $response->assertSee($transaction->order_number);
    }

    public function test_unpaid_order_shows_pending_message(): void
    {
        $transaction = $this->createOnlineOrder(
            user: $this->member,
            status: Transaction::PENDING_PAYMENT,
            paymentStatus: 'pending'
        );

        $response = $this->actingAs($this->member)->get(route('track.invoice', $transaction));

        $response->assertOk();
        $response->assertSee('Invoice Unavailable');
        $response->assertSee('This order is pending payment. Invoice will be available once payment is completed.');
        $response->assertDontSee('Item Description');
    }

    public function test_cancelled_order_shows_cancelled_message(): void
    {
        $transaction = $this->createOnlineOrder(
            user: $this->member,
            status: Transaction::CANCELLED,
            paymentStatus: 'cancel'
        );

        $response = $this->actingAs($this->member)->get(route('track.invoice', $transaction));

        $response->assertOk();
        $response->assertSee('Invoice Unavailable');
        $response->assertSee('This order was cancelled. Invoice cannot be printed.');
        $response->assertDontSee('Item Description');
    }

    public function test_member_cannot_view_another_members_invoice(): void
    {
        $transaction = $this->createOnlineOrder($this->otherMember, Transaction::COMPLETED, 'settlement');

        $response = $this->actingAs($this->member)->get(route('track.invoice', $transaction));

        $response->assertNotFound();
    }

    public function test_member_cannot_view_pos_transaction_invoice(): void
    {
        $posTransaction = Transaction::create([
            'user_id' => $this->member->id,
            'cashier_id' => $this->cashier->id,
            'transaction_type' => Transaction::TYPE_POS,
            'status' => Transaction::COMPLETED,
            'total' => 150000,
        ]);

        Payment::create([
            'transaction_id' => $posTransaction->id,
            'payment_method' => 'cash',
            'payment_status' => 'settlement',
            'amount' => 150000,
            'payment_date' => now(),
        ]);

        $response = $this->actingAs($this->member)->get(route('track.invoice', $posTransaction));

        $response->assertNotFound();
    }

    public function test_track_page_shows_print_invoice_link_for_selected_order(): void
    {
        $transaction = $this->createOnlineOrder($this->member, Transaction::COMPLETED, 'settlement');

        $response = $this->actingAs($this->member)->get(route('track', ['order' => $transaction->order_number]));

        $response->assertOk();
        $response->assertSee('Print invoice');
        $response->assertSee(route('track.invoice', $transaction));
    }

    private function createOnlineOrder(
        User $user,
        string $status,
        string $paymentStatus,
        bool $isDelivery = true,
        string $courier = 'jne',
        int $shippingCost = 0,
        ?string $trackingNumber = null,
        ?string $notes = null
    ): Transaction {
        $itemTotal = 150000;
        $grandTotal = $itemTotal + $shippingCost;

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_type' => Transaction::TYPE_ONLINE,
            'status' => $status,
            'shipping_address' => $isDelivery ? "{$user->name} ({$user->phone})\nJl. Teratai No. 10, Malang" : null,
            'courier' => $isDelivery ? $courier : null,
            'tracking_number' => $trackingNumber,
            'notes' => $notes,
            'total' => $grandTotal,
        ]);

        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'unit_price' => 150000,
            'subtotal' => 150000,
        ]);

        Payment::create([
            'transaction_id' => $transaction->id,
            'midtrans_order_id' => 'FS-'.$transaction->id.'-TESTINVOICE',
            'payment_method' => 'midtrans',
            'payment_status' => $paymentStatus,
            'amount' => $grandTotal,
            'payment_date' => $paymentStatus === 'settlement' ? now() : null,
        ]);

        return $transaction;
    }
}
