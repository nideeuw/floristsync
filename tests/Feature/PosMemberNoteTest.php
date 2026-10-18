<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosMemberNoteTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private Role $memberRole;

    private User $member1;

    private User $member2;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);

        $cashierRole = Role::where('role_name', Role::CASHIER)->firstOrFail();
        $this->memberRole = Role::where('role_name', Role::MEMBER)->firstOrFail();

        $this->cashier = User::factory()->create([
            'name' => 'Cashier Bob',
            'role_id' => $cashierRole->id,
            'is_active' => true,
        ]);

        $this->member1 = User::factory()->create([
            'name' => 'Alice Johnson',
            'phone' => '081234567890',
            'role_id' => $this->memberRole->id,
            'is_active' => true,
        ]);

        $this->member2 = User::factory()->create([
            'name' => 'Charlie Smith',
            'phone' => '089876543210',
            'role_id' => $this->memberRole->id,
            'is_active' => true,
        ]);

        $category = Category::create([
            'category_name' => 'Flowers',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'product_name' => 'Blue Orchid',
            'price' => 45000,
            'stock' => 20,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);
    }

    public function test_cashier_can_search_member_by_phone(): void
    {
        $response = $this->actingAs($this->cashier)->getJson('/api/pos/members?q=081234567890');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $this->member1->id);
        $response->assertJsonPath('data.0.name', 'Alice Johnson');
        $response->assertJsonPath('data.0.phone', '081234567890');
    }

    public function test_cashier_can_search_member_by_id(): void
    {
        // By raw ID
        $response = $this->actingAs($this->cashier)->getJson("/api/pos/members?q={$this->member2->id}");
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $this->member2->id);

        // By prefixed ID (#id)
        $encoded = urlencode("#{$this->member2->id}");
        $response2 = $this->actingAs($this->cashier)->getJson("/api/pos/members?q={$encoded}");
        $response2->assertOk();
        $response2->assertJsonCount(1, 'data');
        $response2->assertJsonPath('data.0.id', $this->member2->id);
    }

    public function test_search_only_returns_active_members(): void
    {
        // Another cashier (non-member)
        $anotherCashier = User::factory()->create([
            'name' => 'Dave Cashier',
            'phone' => '081100000000',
            'role_id' => Role::where('role_name', Role::CASHIER)->value('id'),
            'is_active' => true,
        ]);

        // Inactive member
        $inactiveMember = User::factory()->create([
            'name' => 'Eve Inactive',
            'phone' => '081122223333',
            'role_id' => $this->memberRole->id,
            'is_active' => false,
        ]);

        // Searching non-member phone yields no results
        $resCashier = $this->actingAs($this->cashier)->getJson('/api/pos/members?q=081100000000');
        $resCashier->assertOk();
        $resCashier->assertJsonCount(0, 'data');

        // Searching inactive member yields no results
        $resInactive = $this->actingAs($this->cashier)->getJson('/api/pos/members?q=081122223333');
        $resInactive->assertOk();
        $resInactive->assertJsonCount(0, 'data');
    }

    public function test_non_cashier_cannot_access_members_search(): void
    {
        // As a member
        $response = $this->actingAs($this->member1)->getJson('/api/pos/members?q=Alice');
        $response->assertForbidden();

        // As a guest
        $responseGuest = $this->getJson('/api/pos/members?q=Alice');
        $responseGuest->assertForbidden();
    }

    public function test_cashier_can_create_pos_sale_with_member_and_note(): void
    {
        $payload = [
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 2],
            ],
            'payment_method' => 'cash',
            'paid_amount' => 100000,
            'user_id' => $this->member1->id,
            'notes' => "Card message: Happy Graduation, Alice!\nBest wishes from Mom",
        ];

        $response = $this->actingAs($this->cashier)->postJson('/api/pos/transactions', $payload);

        $response->assertStatus(201);
        $response->assertJson([
            'message' => 'Transaction successful.',
            'data' => [
                'user_id' => $this->member1->id,
                'notes' => "Card message: Happy Graduation, Alice!\nBest wishes from Mom",
                'member' => [
                    'id' => $this->member1->id,
                    'name' => 'Alice Johnson',
                ],
            ],
        ]);

        $txId = $response->json('data.id');
        $tx = Transaction::with('user')->findOrFail($txId);

        $this->assertSame($this->member1->id, $tx->user_id);
        $this->assertSame("Card message: Happy Graduation, Alice!\nBest wishes from Mom", $tx->notes);
        $this->assertSame(Transaction::COMPLETED, $tx->status);
    }

    public function test_pos_sale_works_without_member_and_notes(): void
    {
        $payload = [
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 1],
            ],
            'payment_method' => 'cash',
            'paid_amount' => 50000,
        ];

        $response = $this->actingAs($this->cashier)->postJson('/api/pos/transactions', $payload);

        $response->assertStatus(201);
        $txId = $response->json('data.id');
        $tx = Transaction::findOrFail($txId);

        $this->assertNull($tx->user_id);
        $this->assertNull($tx->notes);
    }

    public function test_cannot_attach_non_member_as_user_id(): void
    {
        $owner = User::factory()->create([
            'role_id' => Role::where('role_name', Role::OWNER)->value('id'),
            'is_active' => true,
        ]);

        $payload = [
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 1],
            ],
            'payment_method' => 'cash',
            'paid_amount' => 50000,
            'user_id' => $owner->id,
        ];

        $response = $this->actingAs($this->cashier)->postJson('/api/pos/transactions', $payload);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'The selected customer must be a member.']);
    }

    public function test_cannot_attach_inactive_member_as_user_id(): void
    {
        $inactiveMember = User::factory()->create([
            'name' => 'Inactive Member',
            'role_id' => $this->memberRole->id,
            'is_active' => false,
        ]);

        $payload = [
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 1],
            ],
            'payment_method' => 'cash',
            'paid_amount' => 50000,
            'user_id' => $inactiveMember->id,
        ];

        $response = $this->actingAs($this->cashier)->postJson('/api/pos/transactions', $payload);

        $response->assertStatus(422);
        $response->assertJson(['message' => 'The selected customer must be a member.']);
    }

    public function test_attached_pos_sale_shows_in_member_order_history(): void
    {
        // 1. Create a sale attached to member1
        $payload = [
            'items' => [
                ['product_id' => $this->product->id, 'qty' => 1],
            ],
            'payment_method' => 'cash',
            'paid_amount' => 50000,
            'user_id' => $this->member1->id,
            'notes' => 'Happy Birthday!',
        ];

        $createResponse = $this->actingAs($this->cashier)->postJson('/api/pos/transactions', $payload);
        $createResponse->assertStatus(201);
        $txId = $createResponse->json('data.id');

        // 2. Member1 queries /api/my/orders
        $historyResponse = $this->actingAs($this->member1)->getJson('/api/my/orders');
        $historyResponse->assertOk();

        $historyIds = collect($historyResponse->json('data'))->pluck('id')->all();
        $this->assertContains($txId, $historyIds);

        // 3. Member2 queries /api/my/orders and does NOT see member1's transaction
        $member2History = $this->actingAs($this->member2)->getJson('/api/my/orders');
        $member2History->assertOk();
        $member2Ids = collect($member2History->json('data'))->pluck('id')->all();
        $this->assertNotContains($txId, $member2Ids);
    }
}
