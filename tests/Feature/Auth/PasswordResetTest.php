<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_password_screen_can_be_rendered(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            $response = $this->get('/reset-password/'.$notification->token);

            $response->assertStatus(200);

            return true;
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });
    }

    public function test_forgot_password_does_not_leak_whether_email_exists(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', [
            'email' => 'doesnotexist@example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', __('passwords.sent'));
        Notification::assertNothingSent();
    }

    public function test_forgot_password_link_host_comes_from_app_url(): void
    {
        config(['app.url' => 'https://floristsync.example.com']);
        Notification::fake();

        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);
            $actionUrl = $mail->actionUrl;

            $this->assertStringStartsWith('https://floristsync.example.com/reset-password/', $actionUrl);
            $this->assertStringContainsString($notification->token, $actionUrl);

            return true;
        });
    }

    public function test_forgot_password_throttled_error_reads_clearly_with_seconds(): void
    {
        $user = User::factory()->create();

        // First request sends link
        $this->post('/forgot-password', ['email' => $user->email]);

        // Second immediate request triggers throttle
        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertSessionHasErrors('email');
        $error = session('errors')->first('email');
        $this->assertMatchesRegularExpression('/^Please wait \d+ seconds before retrying\.$/', $error);
    }

    public function test_staff_cashier_can_reset_password_and_lands_on_cashier_login_door(): void
    {
        Notification::fake();

        $cashierRole = \App\Models\Role::where('role_name', \App\Models\Role::CASHIER)->firstOrFail();
        $cashier = User::factory()->create([
            'email' => 'cashier.unique@example.com',
            'role_id' => $cashierRole->id,
        ]);

        $this->post('/forgot-password', ['email' => $cashier->email]);

        Notification::assertSentTo($cashier, ResetPassword::class, function ($notification) use ($cashier) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $cashier->email,
                'password' => 'NewCashierPass123!',
                'password_confirmation' => 'NewCashierPass123!',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('cashier.login'));

            return true;
        });
    }

    public function test_staff_admin_can_reset_password_and_lands_on_admin_login_door(): void
    {
        Notification::fake();

        $adminRole = \App\Models\Role::where('role_name', \App\Models\Role::ADMIN_ORDER)->firstOrFail();
        $admin = User::factory()->create([
            'email' => 'admin.unique@example.com',
            'role_id' => $adminRole->id,
        ]);

        $this->post('/forgot-password', ['email' => $admin->email]);

        Notification::assertSentTo($admin, ResetPassword::class, function ($notification) use ($admin) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $admin->email,
                'password' => 'NewAdminPass123!',
                'password_confirmation' => 'NewAdminPass123!',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('admin.login'));

            return true;
        });
    }
}
