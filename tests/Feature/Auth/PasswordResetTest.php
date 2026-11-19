<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;
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
                'password' => 'password123',
                'password_confirmation' => 'password123',
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
        $response->assertSessionHas('status', 'If that email is registered, we have sent a password reset link. Please check your inbox and your spam folder.');
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

        $cashierRole = Role::where('role_name', Role::CASHIER)->firstOrFail();
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

        $adminRole = Role::where('role_name', Role::ADMIN_ORDER)->firstOrFail();
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
                ->assertRedirect(route('admin.login'))
                ->assertRedirect('/admin/login');

            return true;
        });
    }

    public function test_tc020_registered_email_sends_link_and_unknown_email_shows_same_message_without_sending(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'registered.user@example.com']);
        $expectedMessage = 'If that email is registered, we have sent a password reset link. Please check your inbox and your spam folder.';

        // Known registered user
        $knownResponse = $this->post('/forgot-password', ['email' => $user->email]);
        $knownResponse->assertSessionHasNoErrors();
        $knownResponse->assertSessionHas('status', $expectedMessage);
        Notification::assertSentTo($user, ResetPassword::class);

        // Unknown unregistered email
        $unknownResponse = $this->post('/forgot-password', ['email' => 'unregistered.ghost@example.com']);
        $unknownResponse->assertSessionHasNoErrors();
        $unknownResponse->assertSessionHas('status', $expectedMessage);

        // Anti account-enumeration: exactly 1 notification sent across both requests (to known user only)
        Notification::assertCount(1);
    }

    public function test_expired_token_is_rejected_and_password_does_not_change(): void
    {
        $user = User::factory()->create([
            'email' => 'expired.token@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $token = Password::broker()->createToken($user);

        // Fast forward beyond token expiry (default 60 mins)
        Carbon::setTestNow(now()->addMinutes(config('auth.passwords.users.expire', 60) + 5));

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame(__(Password::INVALID_TOKEN), session('errors')->first('email'));

        // Password must not change
        $this->assertTrue(Hash::check('OldPassword123!', $user->fresh()->password));
        $this->assertFalse(Hash::check('NewPassword123!', $user->fresh()->password));

        Carbon::setTestNow();
    }

    public function test_already_used_token_is_rejected_and_password_does_not_change(): void
    {
        $user = User::factory()->create([
            'email' => 'used.token@example.com',
            'password' => Hash::make('InitialPassword123!'),
        ]);

        $token = Password::broker()->createToken($user);

        // First use: success
        $firstResponse = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'FirstReset123!',
            'password_confirmation' => 'FirstReset123!',
        ]);

        $firstResponse->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('FirstReset123!', $user->fresh()->password));

        // Second use of same token: rejected
        $secondResponse = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'SecondReset123!',
            'password_confirmation' => 'SecondReset123!',
        ]);

        $secondResponse->assertSessionHasErrors('email');
        $this->assertSame(__(Password::INVALID_TOKEN), session('errors')->first('email'));

        // Password remains the first reset, NOT changed to the second
        $this->assertTrue(Hash::check('FirstReset123!', $user->fresh()->password));
        $this->assertFalse(Hash::check('SecondReset123!', $user->fresh()->password));
    }

    public function test_new_password_works_and_old_password_does_not(): void
    {
        $user = User::factory()->create([
            'email' => 'member.login.test@example.com',
            'password' => Hash::make('OldPassword123!'),
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('login'));

        // Old password login fails
        $failedLogin = $this->post('/login', [
            'email' => $user->email,
            'password' => 'OldPassword123!',
        ]);
        $failedLogin->assertSessionHasErrors('email');
        $this->assertGuest();

        // New password login succeeds
        $successfulLogin = $this->post('/login', [
            'email' => $user->email,
            'password' => 'BrandNewPassword123!',
        ]);
        $successfulLogin->assertSessionHasNoErrors();
        $successfulLogin->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_mail_transport_failure_shows_friendly_error_and_never_500(): void
    {
        $user = User::factory()->create([
            'email' => 'transport.fail@example.com',
        ]);

        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new TransportException('SMTP server connection timed out'));

        $response = $this->from('/forgot-password')->post('/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(302);
        $response->assertRedirect('/forgot-password');
        $response->assertSessionHasErrors([
            'email' => 'We could not send the email right now. Please try again in a few minutes.',
        ]);
    }
}
