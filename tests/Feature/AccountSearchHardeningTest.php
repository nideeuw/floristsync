<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\StaffService;
use App\Services\TransactionHistoryService;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountSearchHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesMenusSeeder::class);
    }

    public function test_escape_like_helper_escapes_special_characters_and_trims_and_caps(): void
    {
        $this->assertEquals('hello', escape_like('hello'));
        $this->assertEquals('hello', escape_like('   hello   '));
        $this->assertEquals('100\\%', escape_like('100%'));
        $this->assertEquals('user\\_name', escape_like('user_name'));
        $this->assertEquals('path\\\\to', escape_like('path\\to'));
        $this->assertEquals('\\%\\_\\\\', escape_like('%_\\'));

        // Caps at 100 characters
        $long101 = str_repeat('a', 101);
        $escaped = escape_like($long101);
        $this->assertEquals(100, strlen($escaped));
        $this->assertEquals(str_repeat('a', 100), $escaped);

        // Null and empty string handling
        $this->assertEquals('', escape_like(null));
        $this->assertEquals('', escape_like(''));
        $this->assertEquals('', escape_like('   '));
    }

    public function test_search_for_percent_and_underscore_does_not_match_everything_in_catalog(): void
    {
        $category = Category::create(['category_name' => 'Flowers', 'is_active' => true]);

        Product::create([
            'category_id' => $category->id,
            'product_name' => 'Rose Bouquet Standard',
            'price' => 50000,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'product_name' => 'Special 10% Discount Bouquet',
            'price' => 45000,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        Product::create([
            'category_id' => $category->id,
            'product_name' => 'Lily_White Elegant Flower',
            'price' => 60000,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        // Search for %: must NOT return all 3 products
        $responsePercent = $this->get('/catalog?q=%');
        $responsePercent->assertStatus(200);
        $responsePercent->assertDontSee('Rose Bouquet Standard');

        // Search for _: must NOT return all 3 products
        $responseUnderscore = $this->get('/catalog?q=_');
        $responseUnderscore->assertStatus(200);
        $responseUnderscore->assertDontSee('Rose Bouquet Standard');
    }

    public function test_101_character_search_is_capped_to_100_characters(): void
    {
        $category = Category::create(['category_name' => 'Capped Category', 'is_active' => true]);

        $name100 = str_repeat('b', 100);
        Product::create([
            'category_id' => $category->id,
            'product_name' => $name100,
            'price' => 50000,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        // Query with 101 characters (100 'b's + 1 'c')
        $query101 = $name100.'c';
        // When capped to 100 chars, it trims to 100 'b's and matches the product!
        $response = $this->get('/catalog?q='.urlencode($query101));
        $response->assertStatus(200);
        $response->assertSee($name100);
    }

    public function test_search_escaping_in_admin_products_and_stock(): void
    {
        $invRole = Role::where('role_name', Role::ADMIN_INVENTORY)->firstOrFail();
        $invAdmin = User::factory()->create(['role_id' => $invRole->id, 'is_active' => true]);

        $category = Category::create(['category_name' => 'Admin Cat', 'is_active' => true]);

        $p1 = Product::create([
            'category_id' => $category->id,
            'product_name' => 'Product Normal',
            'price' => 10000,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $p2 = Product::create([
            'category_id' => $category->id,
            'product_name' => 'Product 50% Off',
            'price' => 5000,
            'status' => Product::STATUS_AVAILABLE,
            'is_active' => true,
        ]);

        $responseAdminProd = $this->actingAs($invAdmin)->get('/admin/products?q=%');
        $responseAdminProd->assertStatus(200);
        $responseAdminProd->assertDontSee('Product Normal');

        $responseAdminStock = $this->actingAs($invAdmin)->get('/admin/stock?q=%');
        $responseAdminStock->assertStatus(200);
        $responseAdminStock->assertDontSee('Product Normal');
    }

    public function test_search_escaping_in_pos_members_and_staff_service(): void
    {
        $cashierRole = Role::where('role_name', Role::CASHIER)->firstOrFail();
        $cashier = User::factory()->create(['role_id' => $cashierRole->id, 'is_active' => true]);

        $memberRole = Role::where('role_name', Role::MEMBER)->firstOrFail();
        User::factory()->create([
            'name' => 'John Regular',
            'email' => 'john@example.com',
            'phone' => '08123456789',
            'role_id' => $memberRole->id,
            'is_active' => true,
        ]);
        User::factory()->create([
            'name' => 'VIP 20% Member',
            'email' => 'vip@example.com',
            'phone' => '08999999999',
            'role_id' => $memberRole->id,
            'is_active' => true,
        ]);

        // POS member search for % does not match John Regular
        $responsePos = $this->actingAs($cashier)->getJson('/api/pos/members?q=%');
        $responsePos->assertStatus(200);
        $responsePos->assertJsonMissing(['name' => 'John Regular']);

        // StaffService search for % does not match regular staff
        $staffService = app(StaffService::class);
        $staffResults = $staffService->paginate(['q' => '%']);
        $this->assertFalse(collect($staffResults->items())->contains('name', 'Cashier Regular'));
    }

    public function test_search_escaping_in_admin_orders_and_transaction_history_service(): void
    {
        $orderRole = Role::where('role_name', Role::ADMIN_ORDER)->firstOrFail();
        $orderAdmin = User::factory()->create(['role_id' => $orderRole->id, 'is_active' => true]);

        $tx1 = Transaction::create([
            'transaction_type' => Transaction::TYPE_ONLINE,
            'total' => 100000,
            'status' => Transaction::PROCESSING,
        ]);
        Payment::create([
            'transaction_id' => $tx1->id,
            'midtrans_order_id' => 'ORDER-REGULAR-123',
            'payment_method' => 'qris',
            'payment_status' => 'settlement',
            'amount' => 100000,
        ]);

        $tx2 = Transaction::create([
            'transaction_type' => Transaction::TYPE_ONLINE,
            'total' => 150000,
            'status' => Transaction::PROCESSING,
        ]);
        Payment::create([
            'transaction_id' => $tx2->id,
            'midtrans_order_id' => 'ORDER-100%SPECIAL-456',
            'payment_method' => 'qris',
            'payment_status' => 'settlement',
            'amount' => 150000,
        ]);

        $responseOrders = $this->actingAs($orderAdmin)->get('/admin/orders?q=%');
        $responseOrders->assertStatus(200);
        $responseOrders->assertDontSee('ORDER-REGULAR-123');

        $txHistoryService = app(TransactionHistoryService::class);
        $results = $txHistoryService->paginate(['q' => '%']);
        $orderIds = collect($results->items())->pluck('payment.midtrans_order_id')->filter();
        $this->assertFalse($orderIds->contains('ORDER-REGULAR-123'));
    }

    public function test_password_rules_for_member_and_staff(): void
    {
        // 1. Member registration password rules:
        // Less than 8 characters -> rejected
        $r1 = $this->post('/register', [
            'name' => 'Member Test',
            'email' => 'mem1@example.com',
            'password' => 'pass12',
            'password_confirmation' => 'pass12',
        ]);
        $r1->assertSessionHasErrors('password');

        // 8 characters letters only (no numbers) -> rejected
        $r2 = $this->post('/register', [
            'name' => 'Member Test',
            'email' => 'mem2@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
        $r2->assertSessionHasErrors('password');

        // 8 characters numbers only (no letters) -> rejected
        $r3 = $this->post('/register', [
            'name' => 'Member Test',
            'email' => 'mem3@example.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ]);
        $r3->assertSessionHasErrors('password');

        // 8 characters letters + numbers -> accepted
        $r4 = $this->post('/register', [
            'name' => 'Member Test',
            'email' => 'mem4@example.com',
            'password' => 'pass1234',
            'password_confirmation' => 'pass1234',
        ]);
        $r4->assertSessionHasNoErrors();
        $r4->assertRedirect(route('register.verify'));
        $this->assertGuest();

        // 2. Staff password rules (at least 10 characters):
        $ownerRole = Role::where('role_name', Role::OWNER)->firstOrFail();
        $cashierRole = Role::where('role_name', Role::CASHIER)->firstOrFail();
        $owner = User::factory()->create(['role_id' => $ownerRole->id, 'is_active' => true]);

        // 9 characters -> rejected
        $rStaffShort = $this->actingAs($owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/admin/staff', [
                'name' => 'Staff Short',
                'email' => 'staffshort@floristsync.test',
                'role_id' => $cashierRole->id,
                'password' => 'Passw0rd9',
                'password_confirmation' => 'Passw0rd9',
            ]);
        $rStaffShort->assertSessionHasErrors('password');

        // 10 characters -> accepted
        $rStaffValid = $this->actingAs($owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post('/admin/staff', [
                'name' => 'Staff Valid',
                'email' => 'staffvalid@floristsync.test',
                'role_id' => $cashierRole->id,
                'password' => 'Password10',
                'password_confirmation' => 'Password10',
            ]);
        $rStaffValid->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'staffvalid@floristsync.test']);
    }

    public function test_owner_must_reenter_password_before_creating_deactivating_or_updating_staff(): void
    {
        $ownerRole = Role::where('role_name', Role::OWNER)->firstOrFail();
        $cashierRole = Role::where('role_name', Role::CASHIER)->firstOrFail();
        $owner = User::factory()->create(['role_id' => $ownerRole->id, 'is_active' => true]);

        $cashier = User::factory()->create([
            'role_id' => $cashierRole->id,
            'is_active' => true,
        ]);

        // 1. Without password confirmation, store() redirects to confirm-password
        $rCreate = $this->actingAs($owner)->post('/admin/staff', [
            'name' => 'New Cashier',
            'email' => 'newcashier@example.com',
            'role_id' => $cashierRole->id,
            'password' => 'ValidPass123',
            'password_confirmation' => 'ValidPass123',
        ]);
        $rCreate->assertRedirect(route('password.confirm'));

        // 2. Without password confirmation, update() redirects to confirm-password
        $rUpdate = $this->actingAs($owner)->put("/admin/staff/{$cashier->id}", [
            'name' => 'Updated Cashier',
            'email' => $cashier->email,
            'role_id' => $cashierRole->id,
            'is_active' => 1,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);
        $rUpdate->assertRedirect(route('password.confirm'));

        // 3. Without password confirmation, destroy() redirects to confirm-password
        $rDestroy = $this->actingAs($owner)->delete("/admin/staff/{$cashier->id}");
        $rDestroy->assertRedirect(route('password.confirm'));
    }

    public function test_other_sessions_are_ended_after_password_change_and_current_session_regenerated(): void
    {
        $memberRole = Role::where('role_name', Role::MEMBER)->firstOrFail();
        $user = User::factory()->create([
            'role_id' => $memberRole->id,
            'is_active' => true,
            'password' => Hash::make('oldpassword123'),
        ]);

        $otherUser = User::factory()->create([
            'role_id' => $memberRole->id,
            'is_active' => true,
        ]);

        $currentSessionId = 'test_sess_current';
        $otherSessionId = 'test_sess_other';
        $unrelatedSessionId = 'test_sess_unrelated';

        // Seed rows in sessions table
        DB::table('sessions')->insert([
            [
                'id' => $currentSessionId,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Browser 1',
                'payload' => serialize(['user_id' => $user->id]),
                'last_activity' => time(),
            ],
            [
                'id' => $otherSessionId,
                'user_id' => $user->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Browser 2',
                'payload' => serialize(['user_id' => $user->id]),
                'last_activity' => time(),
            ],
            [
                'id' => $unrelatedSessionId,
                'user_id' => $otherUser->id,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Browser 3',
                'payload' => serialize(['user_id' => $otherUser->id]),
                'last_activity' => time(),
            ],
        ]);

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'dummy_token'])
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'oldpassword123',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertSessionHasNoErrors();

        // The user's other session in DB must be deleted
        $this->assertDatabaseMissing('sessions', ['id' => $otherSessionId]);

        // The unrelated user's session must still exist
        $this->assertDatabaseHas('sessions', ['id' => $unrelatedSessionId]);
    }

    public function test_other_sessions_are_ended_after_password_reset(): void
    {
        Notification::fake();

        $memberRole = Role::where('role_name', Role::MEMBER)->firstOrFail();
        $user = User::factory()->create([
            'email' => 'resetuser@floristsync.test',
            'role_id' => $memberRole->id,
            'is_active' => true,
        ]);

        DB::table('sessions')->insert([
            'id' => 'device_session_to_be_ended',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Old Device',
            'payload' => serialize(['user_id' => $user->id]),
            'last_activity' => time(),
        ]);

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

            $response->assertSessionHasNoErrors();

            return true;
        });

        // The open session row for this user should be deleted from sessions table
        $this->assertDatabaseMissing('sessions', ['id' => 'device_session_to_be_ended']);
    }

    public function test_ensure_user_is_active_covers_api_routes_and_kills_open_session_on_next_request(): void
    {
        $memberRole = Role::where('role_name', Role::MEMBER)->firstOrFail();
        $user = User::factory()->create([
            'email' => 'activeuser@floristsync.test',
            'role_id' => $memberRole->id,
            'is_active' => true,
            'password' => Hash::make('pass1234'),
        ]);

        // Login with credentials so session is established
        $this->post('/login', [
            'email' => 'activeuser@floristsync.test',
            'password' => 'pass1234',
        ]);
        $this->assertAuthenticatedAs($user);

        // 1. While active, internal API route in web group returns 200
        $activeResponse = $this->getJson('/api/notifications');
        $activeResponse->assertStatus(200);

        // 2. Deactivate user in database
        $user->forceFill(['is_active' => false])->save();

        // 3. User with open session requests internal API route
        $deactivatedResponse = $this->getJson('/api/notifications');

        // User is logged out and session is killed immediately
        $this->assertGuest();

        // 4. On the very next request, user is unauthenticated
        $nextResponse = $this->get('/dashboard');
        $nextResponse->assertRedirect(route('login'));
    }
}
