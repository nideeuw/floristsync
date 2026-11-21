<?php

namespace Tests\Feature\Auth;

use App\Mail\RegistrationCodeMail;
use App\Models\User;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class RegistrationVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'new.member@example.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);
        Mail::fake();
    }

    private function startRegistration(): void
    {
        $this->post(route('register'), [
            'name' => 'New Member',
            'email' => self::EMAIL,
            'password' => 'Flower2026',
            'password_confirmation' => 'Flower2026',
        ])->assertRedirect(route('register.verify'));
    }

    private function lastCode(): string
    {
        $code = null;

        Mail::assertSent(RegistrationCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code ?? throw new RuntimeException('No verification mail was sent.');
    }

    private function wrongCode(string $code): string
    {
        return $code === '000000' ? '111111' : '000000';
    }

    public function test_registering_mails_a_code_and_does_not_create_the_account_yet(): void
    {
        $this->startRegistration();

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => self::EMAIL]);
        Mail::assertSent(RegistrationCodeMail::class, fn ($mail) => $mail->hasTo(self::EMAIL) && strlen($mail->code) === 6);
        $this->get(route('register.verify'))->assertOk()->assertSee('n***@example.test');
    }

    public function test_the_right_code_creates_a_verified_member_and_logs_in(): void
    {
        $this->startRegistration();

        $this->post(route('register.verify.store'), ['code' => $this->lastCode()])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();

        $user = User::where('email', self::EMAIL)->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('New Member', $user->name);
        $this->assertTrue(password_verify('Flower2026', $user->password));
    }

    public function test_a_wrong_code_is_refused_and_creates_nothing(): void
    {
        $this->startRegistration();

        $this->post(route('register.verify.store'), ['code' => $this->wrongCode($this->lastCode())])
            ->assertSessionHasErrors('code');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => self::EMAIL]);
    }

    public function test_a_code_must_be_six_digits(): void
    {
        $this->startRegistration();

        $this->post(route('register.verify.store'), ['code' => '12ab'])->assertSessionHasErrors('code');
    }

    public function test_five_wrong_codes_end_the_registration(): void
    {
        $this->startRegistration();
        $wrong = $this->wrongCode($this->lastCode());

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('register.verify.store'), ['code' => $wrong]);
        }

        $this->get(route('register.verify'))->assertRedirect(route('register'));
        $this->assertDatabaseMissing('users', ['email' => self::EMAIL]);
    }

    public function test_an_expired_code_is_refused_and_a_new_one_can_be_requested(): void
    {
        $this->startRegistration();
        $code = $this->lastCode();

        $this->travel(11)->minutes();

        $this->post(route('register.verify.store'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertDatabaseMissing('users', ['email' => self::EMAIL]);

        $this->post(route('register.verify.resend'))->assertSessionHas('status');
        Mail::assertSent(RegistrationCodeMail::class, 2);
    }

    public function test_a_new_code_cannot_be_requested_too_soon(): void
    {
        $this->startRegistration();

        $this->post(route('register.verify.resend'))->assertSessionHasErrors('code');

        Mail::assertSent(RegistrationCodeMail::class, 1);
    }

    public function test_a_new_code_can_be_requested_after_the_wait(): void
    {
        $this->startRegistration();

        $this->travel(61)->seconds();

        $this->post(route('register.verify.resend'))->assertSessionHas('status');

        Mail::assertSent(RegistrationCodeMail::class, 2);
    }

    public function test_an_email_taken_in_the_meantime_is_refused(): void
    {
        $this->startRegistration();
        $code = $this->lastCode();

        User::factory()->create(['email' => self::EMAIL]);

        $this->post(route('register.verify.store'), ['code' => $code])
            ->assertRedirect(route('register'))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', self::EMAIL)->count());
        $this->assertGuest();
    }

    public function test_the_verify_page_needs_a_started_registration(): void
    {
        $this->get(route('register.verify'))->assertRedirect(route('register'));
        $this->post(route('register.verify.store'), ['code' => '123456'])->assertRedirect(route('register'));
    }

    public function test_use_a_different_email_clears_the_registration_and_keeps_the_typed_values(): void
    {
        $this->startRegistration();

        $this->post(route('register.verify.cancel'))
            ->assertRedirect(route('register'))
            ->assertSessionHasInput('email', self::EMAIL)
            ->assertSessionHasInput('name', 'New Member');

        $this->get(route('register.verify'))->assertRedirect(route('register'));
    }

    public function test_a_mail_failure_shows_an_error_and_stores_nothing(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('mail service down'));

        $this->post(route('register'), [
            'name' => 'New Member',
            'email' => self::EMAIL,
            'password' => 'Flower2026',
            'password_confirmation' => 'Flower2026',
        ])->assertSessionHasErrors('email');

        $this->get(route('register.verify'))->assertRedirect(route('register'));
        $this->assertDatabaseMissing('users', ['email' => self::EMAIL]);
    }
}
