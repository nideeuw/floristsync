<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\ShippingService;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $response->assertSee('Error 404');
        $response->assertSee('Page Not Found');
        $response->assertSee('Back to Home');
    }

    public function test_error_page_uses_staff_layout_for_admin_area(): void
    {
        $response = $this->get('/admin/non-existent-page-xyz');

        $response->assertStatus(404);
        $response->assertSee('Error 404');
        $response->assertSee('Admin Home');
        $response->assertSee('app-sidebar');
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
        $response->assertSee('Error 404');
        $response->assertSee('app-sidebar');
        $response->assertSee('Admin Jane');
    }

    public function test_error_page_uses_pos_layout_for_pos_area(): void
    {
        $response = $this->get('/pos/non-existent-page-xyz');

        $response->assertStatus(404);
        $response->assertSee('Error 404');
        $response->assertSee('POS Terminal');
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
        $response->assertSee('Error 404');
        $response->assertSee('Cashier Bob');
    }

    public function test_login_post_requests_are_throttled_per_ip_and_path(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/login', [
                'email' => 'wrong'.$i.'@example.com',
                'password' => 'wrongpass',
            ]);
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        // 6th attempt to /login should be throttled
        $response = $this->post('/login', [
            'email' => 'another@example.com',
            'password' => 'wrongpass',
        ]);
        $response->assertStatus(429);

        // Different path (/admin/login) from same IP is not blocked by /login attempts
        $adminResponse = $this->post('/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'wrongpass',
        ]);
        $this->assertNotEquals(429, $adminResponse->getStatusCode());
    }

    public function test_pos_login_post_requests_are_throttled_per_ip_and_path(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $response = $this->post('/pos/login', [
                'email' => 'cashier'.$i.'@example.com',
                'password' => 'wrongpass',
            ]);
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        // 6th attempt to /pos/login should be throttled
        $response = $this->post('/pos/login', [
            'email' => 'cashier_another@example.com',
            'password' => 'wrongpass',
        ]);
        $response->assertStatus(429);
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
}
