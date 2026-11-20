<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountReactivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesMenusSeeder::class);
    }

    private function selfDeactivatedMember(): User
    {
        $user = User::factory()->create(['is_active' => false]);
        $user->forceFill(['deactivated_by' => $user->id])->save();

        return $user;
    }

    public function test_self_deactivated_member_can_reactivate_the_account(): void
    {
        $user = $this->selfDeactivatedMember();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('account.reactivate'));

        $this->assertGuest();
        $this->assertFalse((bool) $user->fresh()->is_active);

        $this->get(route('account.reactivate'))
            ->assertOk()
            ->assertSee('Reactivate my account');

        $this->post(route('account.reactivate.store'))
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);

        $fresh = $user->fresh();
        $this->assertTrue((bool) $fresh->is_active);
        $this->assertNull($fresh->deactivated_by);
    }

    public function test_deactivating_then_logging_in_again_round_trip(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->delete('/profile', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('account.reactivate'));

        $this->post(route('account.reactivate.store'))->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue((bool) $user->fresh()->is_active);
    }

    public function test_staff_deactivated_member_stays_blocked(): void
    {
        $staff = User::factory()->create([
            'role_id' => Role::where('role_name', Role::ADMIN_ORDER)->value('id'),
        ]);

        $member = User::factory()->create(['is_active' => false]);
        $member->forceFill(['deactivated_by' => $staff->id])->save();

        $this->post('/login', ['email' => $member->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
        $this->assertFalse((bool) $member->fresh()->is_active);

        $this->get(route('account.reactivate'))->assertRedirect(route('login'));
        $this->post(route('account.reactivate.store'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertFalse((bool) $member->fresh()->is_active);
    }

    public function test_inactive_member_without_deactivated_by_stays_blocked(): void
    {
        $member = User::factory()->create(['is_active' => false]);

        $this->post('/login', ['email' => $member->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_wrong_password_gives_the_generic_error_and_reveals_nothing(): void
    {
        $user = $this->selfDeactivatedMember();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertGuest();
        $this->get(route('account.reactivate'))->assertRedirect(route('login'));

        $this->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => trans('auth.failed')]);

        $this->assertFalse((bool) $user->fresh()->is_active);
    }

    public function test_confirmation_page_needs_a_verified_password_first(): void
    {
        $this->get(route('account.reactivate'))->assertRedirect(route('login'));
        $this->post(route('account.reactivate.store'))->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
