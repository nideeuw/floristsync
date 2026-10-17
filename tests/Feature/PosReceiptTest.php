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

class PosReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private User $member;

    private User $adminOrder;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $cashierRole = Role::where('role_name', Role::CASHIER)->firstOrFail();
        $memberRole = Role::where('role_name', Role::MEMBER)->firstOrFail();
        $adminOrderRole = Role::where('role_name', Role::ADMIN_ORDER)->firstOrFail();

        $this->cashier = User::factory()->create([
            'name' => 'Cashier Sarah',
            'role_id' => $cashierRole->id,
            'is_active' => true,
        ]);

        $this->member = User::factory()->create([
            'name' => 'Member John Doe',
            'role_id' => $memberRole->id,
            'is_active' => true,
        ]);

        $this->adminOrder = User::factory()->create([
            'name' => 'Admin Olivia',
            'role_id' => $adminOrderRole->id,
            'is_active' => true,
        ]);

        $category = Category::create([
            'category_name' => 'Bouquets',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'product_name' => 'Romantic Rose Bouquet',
            'price' => 75000,
            'stock' => 15,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        Setting::updateOrCreate(
            ['setting_key' => 'store_name'],
            ['setting_value' => 'FloristSync Premium Flowers', 'is_active' => true]
        );

        Setting::updateOrCreate(
            ['setting_key' => 'address'],
            ['setting_value' => 'Jl. Mawar No. 88, Jakarta Selatan', 'is_active' => true]
        );

        Setting::updateOrCreate(
            ['setting_key' => 'phone'],
            ['setting_value' => '021-5551234', 'is_active' => true]
        );
    }

    public function test_only_cashier_role_can_open_receipt(): void
    {
        $tx = $this->createCompletedPosSale();

        // 1. Member gets 403
        $this->actingAs($this->member)
            ->get(route('cashier.receipt', $tx))
            ->assertForbidden();

        // 2. Admin Order gets 403
        $this->actingAs($this->adminOrder)
            ->get(route('cashier.receipt', $tx))
            ->assertForbidden();

        // 3. Guest gets 403 Forbidden
        $this->get(route('cashier.receipt', $tx))
            ->assertForbidden();

        // 4. Cashier succeeds
        $this->actingAs($this->cashier)
            ->get(route('cashier.receipt', $tx))
            ->assertOk();
    }

    public function test_cannot_open_receipt_for_online_transaction(): void
    {
        $onlineTx = Transaction::create([
            'user_id' => $this->member->id,
            'transaction_type' => Transaction::TYPE_ONLINE,
            'status' => Transaction::COMPLETED,
            'total' => 75000,
        ]);

        Payment::create([
            'transaction_id' => $onlineTx->id,
            'payment_method' => 'gopay',
            'payment_status' => 'settlement',
            'amount' => 75000,
            'payment_date' => now(),
        ]);

        $this->actingAs($this->cashier)
            ->get(route('cashier.receipt', $onlineTx))
            ->assertNotFound();
    }

    public function test_completed_pos_sale_shows_store_details_cashier_items_totals_and_note(): void
    {
        $tx = $this->createCompletedPosSale([
            'user_id' => $this->member->id,
            'notes' => 'Happy Anniversary, my love!',
        ]);

        $response = $this->actingAs($this->cashier)
            ->get(route('cashier.receipt', ['transaction' => $tx, 'paid' => 200000]));

        $response->assertOk();

        // Store info from settings
        $response->assertSeeText('FloristSync Premium Flowers');
        $response->assertSeeText('Jl. Mawar No. 88, Jakarta Selatan');
        $response->assertSeeText('021-5551234');

        // Cashier and metadata
        $response->assertSeeText('Cashier Sarah');
        $response->assertSeeText($tx->order_number);

        // Member name
        $response->assertSeeText('Member John Doe');

        // Item details
        $response->assertSeeText('Romantic Rose Bouquet');
        $response->assertSeeText('2 x');

        // Totals and cash change
        $response->assertSeeText('TOTAL');
        $response->assertSeeText('Payment');
        $response->assertSeeText('CASH');
        $response->assertSeeText('Change');

        // Special note
        $response->assertSeeText('Special Note:');
        $response->assertSeeText('Happy Anniversary, my love!');

        // Action buttons
        $response->assertSeeText('Print Receipt');
        $response->assertSeeText('Back to POS');
    }

    public function test_pending_pos_sale_shows_message_instead_of_receipt(): void
    {
        $tx = Transaction::create([
            'cashier_id' => $this->cashier->id,
            'transaction_type' => Transaction::TYPE_POS,
            'status' => Transaction::PENDING_PAYMENT,
            'total' => 75000,
        ]);

        Payment::create([
            'transaction_id' => $tx->id,
            'payment_method' => 'qris',
            'payment_status' => 'pending',
            'amount' => 75000,
        ]);

        $response = $this->actingAs($this->cashier)
            ->get(route('cashier.receipt', $tx));

        $response->assertOk();
        $response->assertSeeText('Receipt Unavailable');
        $response->assertSeeText('pending payment');
        $response->assertDontSeeText('Print Receipt');
    }

    public function test_cancelled_pos_sale_shows_message_instead_of_receipt(): void
    {
        $tx = Transaction::create([
            'cashier_id' => $this->cashier->id,
            'transaction_type' => Transaction::TYPE_POS,
            'status' => Transaction::CANCELLED,
            'total' => 75000,
        ]);

        Payment::create([
            'transaction_id' => $tx->id,
            'payment_method' => 'cash',
            'payment_status' => 'cancel',
            'amount' => 75000,
        ]);

        $response = $this->actingAs($this->cashier)
            ->get(route('cashier.receipt', $tx));

        $response->assertOk();
        $response->assertSeeText('Receipt Unavailable');
        $response->assertSeeText('cancelled');
        $response->assertDontSeeText('Print Receipt');
    }

    private function createCompletedPosSale(array $attributes = []): Transaction
    {
        $tx = Transaction::create(array_merge([
            'cashier_id' => $this->cashier->id,
            'transaction_type' => Transaction::TYPE_POS,
            'status' => Transaction::COMPLETED,
            'total' => 150000,
        ], $attributes));

        TransactionDetail::create([
            'transaction_id' => $tx->id,
            'product_id' => $this->product->id,
            'qty' => 2,
            'unit_price' => 75000,
            'subtotal' => 150000,
        ]);

        Payment::create([
            'transaction_id' => $tx->id,
            'payment_method' => 'cash',
            'payment_status' => 'settlement',
            'amount' => 150000,
            'payment_date' => now(),
        ]);

        return $tx;
    }
}
