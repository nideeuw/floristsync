<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ShippingService;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesAndHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesMenusSeeder::class);
        RateLimiter::clear('127.0.0.1|login');
        RateLimiter::clear('127.0.0.1|admin/login');
        RateLimiter::clear('127.0.0.1|pos/login');
    }

    protected function tearDown(): void
    {
        TrustProxies::at(env('TRUSTED_PROXIES', '*'));
        parent::tearDown();
    }

    public function test_error_views_can_be_rendered(): void
    {
        foreach ([403, 404, 419, 429, 500, 503] as $code) {
            $view = view("errors.{$code}")->render();
            $this->assertStringContainsString((string) $code, $view);
        }
    }

    public function test_error_page_uses_public_layout_for_public_area(): void
    {
        $response = $this->get('/non-existent-page-xyz');

        $response->assertStatus(404);
        $response->assertSee('404');
        $response->assertSee('Page Not Found');
        $response->assertSee('Back to Home');
    }

    public function test_error_page_uses_staff_layout_for_admin_area(): void
    {
        $response = $this->get('/admin/non-existent-page-xyz');

        $response->assertStatus(404);
        $response->assertSee('404');
        $response->assertSee('Back to shop');
    }

    public function test_error_page_uses_staff_layout_for_authenticated_admin(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Jane',
            'role_id' => Role::where('role_name', Role::OWNER)->value('id'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/admin/non-existent-page-xyz');

        $response->assertStatus(404);
        $response->assertSee('404');
        $response->assertSee('app-sidebar');
        $response->assertSee('Admin Jane');
    }

    public function test_error_page_uses_pos_layout_for_pos_area(): void
    {
        $response = $this->get('/pos/non-existent-page-xyz');

        $response->assertStatus(404);
        $response->assertSee('404');
        $response->assertSee('Back to shop');
    }

    public function test_error_page_uses_pos_layout_for_authenticated_cashier(): void
    {
        $cashier = User::factory()->create([
            'name' => 'Cashier Bob',
            'role_id' => Role::where('role_name', Role::CASHIER)->value('id'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($cashier)->get('/pos/non-existent-page-xyz');

        $response->assertStatus(404);
        $response->assertSee('404');
        $response->assertSee('Cashier Bob');
        $response->assertSee('POS Terminal');
    }

    public function test_five_wrong_passwords_then_sixth_is_blocked_even_with_right_password(): void
    {
        $user = User::factory()->create([
            'email' => 'victim@example.com',
            'password' => bcrypt('correct-password'),
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-pass-'.$i,
            ]);
            $response->assertSessionHasErrors('email');
            $this->assertGuest();
        }

        // 6th attempt with correct password is still blocked
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
    }

    public function test_another_email_from_same_ip_is_not_blocked(): void
    {
        $user1 = User::factory()->create([
            'email' => 'blocked@example.com',
            'password' => bcrypt('password123'),
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        $user2 = User::factory()->create([
            'email' => 'allowed@example.com',
            'password' => bcrypt('password123'),
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => $user1->email,
                'password' => 'wrong-pass',
            ]);
        }

        // User 1 is blocked
        $this->post('/login', [
            'email' => $user1->email,
            'password' => 'password123',
        ]);
        $this->assertGuest();

        // User 2 from the same IP is not blocked
        $response = $this->post('/login', [
            'email' => $user2->email,
            'password' => 'password123',
        ]);
        $this->assertAuthenticatedAs($user2);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_a_success_clears_the_counter(): void
    {
        $user = User::factory()->create([
            'email' => 'clear_test@example.com',
            'password' => bcrypt('password123'),
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        // 4 failed attempts
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-pass',
            ]);
            $this->assertGuest();
        }

        // 5th attempt succeeds
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);
        $this->assertAuthenticatedAs($user);

        // Logout
        $this->post('/logout');
        $this->assertGuest();

        // User can fail again up to 4 times without being locked out
        for ($i = 0; $i < 4; $i++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-pass',
            ]);
            $this->assertGuest();
        }

        // Next attempt with right password succeeds because previous counter was cleared
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_five_successful_logins_in_one_minute_from_one_ip_are_all_allowed(): void
    {
        $users = User::factory()->count(5)->create([
            'password' => bcrypt('password123'),
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        foreach ($users as $user) {
            $response = $this->post('/login', [
                'email' => $user->email,
                'password' => 'password123',
            ]);

            $this->assertNotEquals(429, $response->getStatusCode());
            $this->assertAuthenticatedAs($user);
            $response->assertRedirect(route('dashboard', absolute: false));

            $this->post('/logout');
            $this->assertGuest();
        }
    }

    public function test_spoofed_x_forwarded_for_does_not_create_new_bucket_when_proxies_are_restricted(): void
    {
        TrustProxies::at('10.0.0.1');

        $user = User::factory()->create([
            'email' => 'spoof_target@example.com',
            'password' => bcrypt('target-pass'),
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        // 5 failed attempts with varying spoofed X-Forwarded-For headers
        for ($i = 1; $i <= 5; $i++) {
            $this->withHeaders(['X-Forwarded-For' => "198.51.100.{$i}"])
                ->post('/login', [
                    'email' => $user->email,
                    'password' => 'wrong-pass',
                ]);
            $this->assertGuest();
        }

        // 6th attempt with correct password and yet another spoofed IP is STILL blocked
        $response = $this->withHeaders(['X-Forwarded-For' => '203.0.113.99'])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'target-pass',
            ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
    }

    public function test_coarse_failure_limit_triggers_429_page_after_max_failed_attempts(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $response = $this->post('/login', [
                'email' => 'spray'.$i.'@example.com',
                'password' => 'wrongpass',
            ]);
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        // 31st failed attempt from the same IP triggers coarse rate limit (429)
        $response = $this->post('/login', [
            'email' => 'spray_another@example.com',
            'password' => 'wrongpass',
        ]);
        $response->assertStatus(429);
        $response->assertSee('429');
    }

    public function test_inactive_member_is_logged_out_and_redirected_to_login(): void
    {
        $member = User::factory()->create([
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        $this->actingAs($member);

        // Deactivate user
        $member->is_active = false;
        $member->save();

        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $response->assertSessionHasErrors(['email', 'account']);
    }

    public function test_inactive_staff_is_logged_out_and_redirected_to_admin_login(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::where('role_name', Role::OWNER)->value('id'),
            'is_active' => true,
        ]);

        $this->actingAs($admin);

        // Deactivate user
        $admin->is_active = false;
        $admin->save();

        $response = $this->get('/admin');

        $response->assertRedirect(route('admin.login'));
        $this->assertGuest();
        $response->assertSessionHasErrors(['email', 'account']);
    }

    public function test_inactive_cashier_is_logged_out_and_redirected_to_cashier_login(): void
    {
        $cashier = User::factory()->create([
            'role_id' => Role::where('role_name', Role::CASHIER)->value('id'),
            'is_active' => true,
        ]);

        $this->actingAs($cashier);

        // Deactivate cashier
        $cashier->is_active = false;
        $cashier->save();

        $response = $this->get('/pos');

        $response->assertRedirect(route('cashier.login'));
        $this->assertGuest();
        $response->assertSessionHasErrors(['email', 'account']);
    }

    public function test_shipping_controller_runtime_exception_returns_json_not_html(): void
    {
        $member = User::factory()->create([
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        $mockShipping = Mockery::mock(ShippingService::class);
        $mockShipping->shouldReceive('searchDestination')
            ->andThrow(new RuntimeException('RajaOngkir connection timed out.'));
        $mockShipping->shouldReceive('calculateWeight')
            ->andReturn(500);
        $mockShipping->shouldReceive('calculateCost')
            ->andThrow(new RuntimeException('Shipping service is unavailable.'));

        $this->app->instance(ShippingService::class, $mockShipping);

        // Category & Product for cost validation exists check
        $category = Category::create([
            'category_name' => 'Roses',
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'product_name' => 'Red Rose Bouquet',
            'slug' => 'red-rose-bouquet',
            'price' => 150000,
            'is_active' => true,
        ]);

        // Test destinations endpoint
        $destResponse = $this->actingAs($member)->get('/api/shipping/destinations?q=Jakarta');
        $destResponse->assertStatus(502);
        $destResponse->assertJson(['message' => 'RajaOngkir connection timed out.']);
        $this->assertStringContainsString('application/json', (string) $destResponse->headers->get('Content-Type'));

        // Test cost endpoint
        $costResponse = $this->actingAs($member)->post('/api/shipping/cost', [
            'destination_id' => 1234,
            'courier' => 'jne',
            'items' => [
                ['product_id' => $product->id, 'qty' => 1],
            ],
        ]);
        $costResponse->assertStatus(502);
        $costResponse->assertJson(['message' => 'Shipping service is unavailable.']);
        $this->assertStringContainsString('application/json', (string) $costResponse->headers->get('Content-Type'));
    }

    public function test_logged_in_track_admin_and_pos_carry_no_cache_headers(): void
    {
        $member = User::factory()->create([
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);
        $admin = User::factory()->create([
            'role_id' => Role::where('role_name', Role::OWNER)->value('id'),
            'is_active' => true,
        ]);
        $cashier = User::factory()->create([
            'role_id' => Role::where('role_name', Role::CASHIER)->value('id'),
            'is_active' => true,
        ]);

        // Member on /track
        $responseMember = $this->actingAs($member)->get('/track');
        $responseMember->assertStatus(200);
        $this->assertNoCacheHeaders($responseMember);

        // Admin on /admin
        $responseAdmin = $this->actingAs($admin)->get('/admin');
        $this->assertNoCacheHeaders($responseAdmin);

        // Cashier on /pos
        $responseCashier = $this->actingAs($cashier)->get('/pos');
        $responseCashier->assertStatus(200);
        $this->assertNoCacheHeaders($responseCashier);
    }

    public function test_guest_home_page_does_not_carry_no_cache_headers(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringNotContainsString('no-store', $cacheControl);
        $this->assertNotEquals('no-cache', $response->headers->get('Pragma'));
    }

    public function test_after_logout_get_track_redirects_to_login(): void
    {
        $member = User::factory()->create([
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        $this->actingAs($member);

        // Member logs out
        $logoutResponse = $this->post('/logout');
        $logoutResponse->assertRedirect(route('login'));
        $this->assertGuest();

        // Browser Back button to /track requires re-authentication and sends to /login
        $backResponse = $this->get('/track');
        $backResponse->assertRedirect(route('login'));
    }

    public function test_after_admin_and_cashier_logout_back_redirects_to_correct_login_doors(): void
    {
        $admin = User::factory()->create([
            'role_id' => Role::where('role_name', Role::OWNER)->value('id'),
            'is_active' => true,
        ]);
        $this->actingAs($admin)->post('/admin/logout');
        $this->assertGuest();

        // Admin Back to /admin redirects to admin.login
        $this->get('/admin')->assertRedirect(route('admin.login'));

        $cashier = User::factory()->create([
            'role_id' => Role::where('role_name', Role::CASHIER)->value('id'),
            'is_active' => true,
        ]);
        $this->actingAs($cashier)->post('/pos/logout');
        $this->assertGuest();

        // Cashier Back to /pos redirects to cashier.login
        $this->get('/pos')->assertRedirect(route('cashier.login'));
    }

    public function test_three_login_pages_carry_no_cache_headers_for_guests(): void
    {
        foreach (['/login', '/admin/login', '/pos/login'] as $door) {
            $response = $this->get($door);
            $response->assertStatus(200);
            $this->assertNoCacheHeaders($response);
        }
    }

    public function test_guest_catalog_and_printable_responses_do_not_carry_no_cache_headers(): void
    {
        // Guest pages
        $catResponse = $this->get('/catalog');
        $catResponse->assertStatus(200);
        $this->assertStringNotContainsString('no-store', (string) $catResponse->headers->get('Cache-Control'));

        // Printable invoice for logged-in user
        $member = User::factory()->create([
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);
        $tx = Transaction::create([
            'user_id' => $member->id,
            'transaction_type' => Transaction::TYPE_ONLINE,
            'total' => 150000,
            'status' => Transaction::PROCESSING,
        ]);

        $invoiceResponse = $this->actingAs($member)->get("/track/{$tx->id}/invoice");
        $invoiceResponse->assertStatus(200);
        $this->assertStringNotContainsString('no-store', (string) $invoiceResponse->headers->get('Cache-Control'));
    }

    private function assertNoCacheHeaders($response): void
    {
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertEquals('no-cache', $response->headers->get('Pragma'));
        $this->assertEquals('0', $response->headers->get('Expires'));
    }
}
