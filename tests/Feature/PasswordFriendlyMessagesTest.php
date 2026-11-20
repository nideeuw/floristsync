<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordFriendlyMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);
    }

    private function register(string $password): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('register'), [
            'name' => 'New Member',
            'email' => 'new.member@example.test',
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    public function test_a_short_password_gets_a_plain_message(): void
    {
        $this->register('abc1')->assertSessionHasErrors('password');

        $this->assertStringContainsString('at least 8 characters', session('errors')->first('password'));
        $this->assertDatabaseMissing('users', ['email' => 'new.member@example.test']);
    }

    public function test_a_password_without_a_number_gets_a_plain_message(): void
    {
        $this->register('abcdefghij')->assertSessionHasErrors('password');

        $this->assertStringContainsString('at least one number', session('errors')->first('password'));
    }

    public function test_a_password_without_a_letter_gets_a_plain_message(): void
    {
        $this->register('1234567890')->assertSessionHasErrors('password');

        $this->assertStringContainsString('at least one letter', session('errors')->first('password'));
    }

    public function test_a_valid_password_still_registers(): void
    {
        $this->register('Flower2026')->assertSessionHasNoErrors();

        $this->assertInstanceOf(User::class, User::where('email', 'new.member@example.test')->first());
    }

    public function test_the_register_and_reset_pages_show_the_strength_hint(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('data-password-strength', false)
            ->assertSee('At least 8 characters')
            ->assertSee('Contains a number');

        $this->get(route('password.reset', ['token' => 'test-token']))
            ->assertOk()
            ->assertSee('data-password-strength', false);
    }
}
