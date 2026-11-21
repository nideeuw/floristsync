<?php

namespace Tests\Feature;

use App\Mail\EmailChangeCodeMail;
use App\Models\User;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/** N6: a new email from the profile page replaces the old one only after the mailed code is entered. */
class EmailChangeVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_EMAIL = 'new-address@example.test';

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);
        Mail::fake();

        $this->member = User::factory()->unverified()->create([
            'is_active' => true,
            'name' => 'Old Name',
            'email' => 'old-address@example.test',
        ]);
    }

    private function startChange(string $email = self::NEW_EMAIL, string $name = 'New Name'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->member)->patch('/profile', [
            'name' => $name,
            'email' => $email,
            'current_password' => 'password',
        ]);
    }

    private function lastCode(): string
    {
        $code = null;

        Mail::assertSent(EmailChangeCodeMail::class, function ($mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        return $code ?? throw new RuntimeException('No verification mail was sent.');
    }

    private function wrongCode(string $code): string
    {
        return $code === '000000' ? '111111' : '000000';
    }

    public function test_a_new_email_is_not_saved_until_the_code_is_entered(): void
    {
        $this->startChange()->assertSessionHasNoErrors()->assertRedirect(route('profile.email.verify'));

        $this->member->refresh();
        $this->assertSame('old-address@example.test', $this->member->email);
        $this->assertSame('New Name', $this->member->name); // the other fields are saved right away
        Mail::assertSent(EmailChangeCodeMail::class, fn ($mail) => $mail->hasTo(self::NEW_EMAIL) && strlen($mail->code) === 6);
        $this->actingAs($this->member)->get(route('profile.email.verify'))->assertOk()->assertSee(self::NEW_EMAIL);
    }

    public function test_the_right_code_switches_to_the_new_verified_email(): void
    {
        $this->startChange();

        $this->actingAs($this->member)
            ->post(route('profile.email.verify.store'), ['code' => $this->lastCode()])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('status', 'email-updated');

        $this->member->refresh();
        $this->assertSame(self::NEW_EMAIL, $this->member->email);
        $this->assertNotNull($this->member->email_verified_at);
    }

    public function test_a_wrong_code_is_refused_and_the_old_email_stays(): void
    {
        $this->startChange();

        $this->actingAs($this->member)
            ->post(route('profile.email.verify.store'), ['code' => $this->wrongCode($this->lastCode())])
            ->assertSessionHasErrors('code');

        $this->assertSame('old-address@example.test', $this->member->fresh()->email);
    }

    public function test_five_wrong_codes_end_the_change(): void
    {
        $this->startChange();
        $wrong = $this->wrongCode($this->lastCode());

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->member)->post(route('profile.email.verify.store'), ['code' => $wrong]);
        }

        $this->actingAs($this->member)->get(route('profile.email.verify'))->assertRedirect(route('profile.edit'));
        $this->assertSame('old-address@example.test', $this->member->fresh()->email);
    }

    public function test_an_expired_code_is_refused_and_a_new_one_can_be_requested(): void
    {
        $this->startChange();
        $code = $this->lastCode();

        $this->travel(11)->minutes();

        $this->actingAs($this->member)->post(route('profile.email.verify.store'), ['code' => $code])
            ->assertSessionHasErrors('code');
        $this->assertSame('old-address@example.test', $this->member->fresh()->email);

        $this->actingAs($this->member)->post(route('profile.email.resend'))->assertSessionHas('status');
        Mail::assertSent(EmailChangeCodeMail::class, 2);
    }

    public function test_a_new_code_cannot_be_requested_too_soon(): void
    {
        $this->startChange();

        $this->actingAs($this->member)->post(route('profile.email.resend'))->assertSessionHasErrors('code');

        Mail::assertSent(EmailChangeCodeMail::class, 1);
    }

    public function test_an_email_taken_in_the_meantime_is_refused(): void
    {
        $this->startChange();
        $code = $this->lastCode();

        User::factory()->create(['email' => self::NEW_EMAIL]);

        $this->actingAs($this->member)->post(route('profile.email.verify.store'), ['code' => $code])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasErrors('email');

        $this->assertSame('old-address@example.test', $this->member->fresh()->email);
    }

    public function test_keeping_the_current_email_drops_the_waiting_change(): void
    {
        $this->startChange();

        $this->actingAs($this->member)->post(route('profile.email.cancel'))->assertRedirect(route('profile.edit'));

        $this->actingAs($this->member)->get(route('profile.email.verify'))->assertRedirect(route('profile.edit'));
        $this->assertSame('old-address@example.test', $this->member->fresh()->email);
    }

    public function test_another_member_cannot_use_the_waiting_change(): void
    {
        $this->startChange();
        $code = $this->lastCode();

        $other = User::factory()->create(['is_active' => true]);

        $this->actingAs($other)->get(route('profile.email.verify'))->assertRedirect(route('profile.edit'));
        $this->actingAs($other)->post(route('profile.email.verify.store'), ['code' => $code]);

        $this->assertNotSame(self::NEW_EMAIL, $other->fresh()->email);
        $this->assertSame('old-address@example.test', $this->member->fresh()->email);
    }

    public function test_a_mail_failure_shows_an_error_and_saves_nothing(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('mail service down'));

        $this->startChange()->assertSessionHasErrors('email');

        $this->member->refresh();
        $this->assertSame('old-address@example.test', $this->member->email);
        $this->assertSame('Old Name', $this->member->name);
    }

    public function test_only_five_changes_can_be_started_per_hour(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->startChange()->assertSessionHasNoErrors();
        }

        $this->startChange()->assertSessionHasErrors('email');
    }

    public function test_an_unchanged_email_sends_no_code(): void
    {
        $this->actingAs($this->member)->patch('/profile', [
            'name' => 'Renamed',
            'email' => 'old-address@example.test',
        ])->assertRedirect('/profile');

        Mail::assertNothingSent();
        $this->assertSame('Renamed', $this->member->fresh()->name);
    }
}
