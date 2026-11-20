<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesMenusSeeder::class);
    }

    private function assertSecurityHeaders(TestResponse $response): void
    {
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
        $response->assertHeader('Content-Security-Policy-Report-Only');
    }

    public function test_headers_exist_on_home_and_both_login_pages(): void
    {
        foreach (['/', '/login', '/admin/login'] as $url) {
            $this->assertSecurityHeaders($this->get($url));
        }
    }

    public function test_headers_exist_on_a_json_route(): void
    {
        $member = User::factory()->create([
            'role_id' => Role::where('role_name', Role::MEMBER)->value('id'),
            'is_active' => true,
        ]);

        $this->assertSecurityHeaders($this->actingAs($member)->getJson('/api/notifications'));
    }

    public function test_csp_is_report_only_and_allows_payment_and_map_hosts(): void
    {
        $response = $this->get('/login');

        $response->assertHeaderMissing('Content-Security-Policy');

        $csp = (string) $response->headers->get('Content-Security-Policy-Report-Only');
        foreach ([
            'https://app.sandbox.midtrans.com',
            'https://app.midtrans.com',
            'https://api.sandbox.midtrans.com',
            'https://api.midtrans.com',
            'https://tile.openstreetmap.org',
            "frame-ancestors 'self'",
        ] as $expected) {
            $this->assertStringContainsString($expected, $csp);
        }
    }

    public function test_hsts_is_absent_in_the_testing_environment(): void
    {
        $this->get('https://localhost/login')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_is_sent_only_in_production_over_https(): void
    {
        $this->app['env'] = 'production';

        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');

        $this->get('http://localhost/login')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_api_products_returns_json_429_after_the_limit(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/products');
        }

        $response = $this->getJson('/api/products');

        $response->assertStatus(429);
        $response->assertJsonStructure(['message']);
        $response->assertHeader('Retry-After');
    }

    public function test_forgot_password_is_limited_to_three_per_minute(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/forgot-password', ['email' => 'nobody@example.com']);
        }

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertStatus(429);
    }

    public function test_register_is_limited_to_five_per_minute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register', []);
        }

        $this->post('/register', [])->assertStatus(429);
    }
}
