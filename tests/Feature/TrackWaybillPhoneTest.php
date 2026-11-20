<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrackWaybillPhoneTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        config([
            'services.rajaongkir.base_url' => 'https://rajaongkir.test/api/v1',
            'services.rajaongkir.api_key' => 'test-key',
        ]);

        $this->member = User::factory()->create([
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => Category::create(['category_name' => 'Flowers', 'is_active' => true])->id,
            'product_name' => 'Red Roses Bouquet',
            'price' => 120000,
            'stock' => 20,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);
    }

    private function shippedOrder(array $extra = []): Transaction
    {
        $transaction = Transaction::create(array_merge([
            'user_id' => $this->member->id,
            'transaction_type' => Transaction::TYPE_ONLINE,
            'status' => Transaction::SHIPPED,
            'shipping_address' => 'Member (081234567890)'."\n".'Jl. Mawar 1',
            'courier' => 'jne',
            'shipping_cost' => 25000,
            'shipping_service' => 'REG',
            'tracking_number' => 'JNE123456789',
            'total' => 145000,
        ], $extra));

        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'unit_price' => 120000,
            'subtotal' => 120000,
        ]);

        Payment::create([
            'transaction_id' => $transaction->id,
            'midtrans_order_id' => 'FS-TEST-'.$transaction->id.'-'.uniqid(),
            'payment_method' => 'midtrans',
            'payment_status' => 'settlement',
            'amount' => 145000,
            'payment_date' => now(),
        ]);

        return $transaction;
    }

    private function openTrackPage(Transaction $transaction)
    {
        return $this->actingAs($this->member)
            ->get(route('track', ['order' => $transaction->order_number]));
    }

    public function test_waybill_request_contains_the_last_five_phone_digits(): void
    {
        // Empty data makes the service throw "unavailable"; the page must still render.
        Http::fake(['*' => Http::response(['meta' => ['code' => 200], 'data' => []], 200)]);

        $transaction = $this->shippedOrder([
            'destination_id' => 123,
            'shipping_origin_id' => 501,
            'shipping_phone' => '081234567890',
        ]);

        $this->openTrackPage($transaction)->assertOk()->assertSee('JNE123456789');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'track/waybill')
            && str_contains($request->url(), 'last_phone_number=67890')
            && ($request->data()['last_phone_number'] ?? null) === '67890');
    }

    public function test_old_order_without_the_new_values_still_renders_and_sends_no_phone(): void
    {
        Http::fake(['*' => Http::response(['meta' => ['code' => 200], 'data' => []], 200)]);

        $transaction = $this->shippedOrder();

        $this->assertNull($transaction->shipping_phone);
        $this->assertNull($transaction->destination_id);

        $this->openTrackPage($transaction)->assertOk()->assertSee('JNE123456789');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'track/waybill')
            && ! str_contains($request->url(), 'last_phone_number'));
    }

    public function test_tracking_failure_still_never_breaks_the_page(): void
    {
        Http::fake(['*' => Http::response(['meta' => ['code' => 500]], 500)]);

        $transaction = $this->shippedOrder(['shipping_phone' => '081234567890']);

        $this->openTrackPage($transaction)
            ->assertOk()
            ->assertSee($transaction->order_number)
            ->assertSee('JNE123456789');
    }
}
