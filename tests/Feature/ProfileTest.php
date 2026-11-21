<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'current_password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.email.verify'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertNotSame('test@example.com', $user->email);
    }

    public function test_name_can_be_updated_without_password_when_email_is_unchanged(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Only Name Changed',
                'email' => $user->email,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertSame('Only Name Changed', $user->fresh()->name);
    }

    public function test_changing_email_without_password_is_rejected(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $oldEmail = $user->email;

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'new-address@example.com',
            ])
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/profile');

        $this->assertSame($oldEmail, $user->fresh()->email);
    }

    public function test_changing_email_with_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $oldEmail = $user->email;

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/profile', [
                'name' => $user->name,
                'email' => 'new-address@example.com',
                'current_password' => 'wrong-password',
            ])
            ->assertSessionHasErrors('current_password')
            ->assertRedirect('/profile');

        $this->assertSame($oldEmail, $user->fresh()->email);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNotNull($user->fresh());
        $this->assertFalse((bool) $user->fresh()->is_active);
        $this->assertSame($user->id, (int) $user->fresh()->deactivated_by);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
