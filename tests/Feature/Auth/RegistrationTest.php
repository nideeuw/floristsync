<?php

namespace Tests\Feature\Auth;

use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_with_existing_member_email_is_rejected_with_neutral_message(): void
    {
        \App\Models\User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->post('/register', [
            'name' => 'Another User',
            'email' => 'existing@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'This email is already registered. Try logging in or use another email.',
        ]);
        $this->assertGuest();
    }

    public function test_registration_with_existing_staff_email_is_rejected_neutrally_without_mentioning_roles(): void
    {
        $adminRole = \App\Models\Role::where('role_name', \App\Models\Role::ADMIN_ORDER)->firstOrFail();
        \App\Models\User::factory()->create([
            'email' => 'admin@floristsync.test',
            'role_id' => $adminRole->id,
        ]);

        $response = $this->post('/register', [
            'name' => 'Impostor Admin',
            'email' => 'admin@floristsync.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors([
            'email' => 'This email is already registered. Try logging in or use another email.',
        ]);
        $this->assertGuest();
    }
}
