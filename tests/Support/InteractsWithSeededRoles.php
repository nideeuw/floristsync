<?php

namespace Tests\Support;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesMenusSeeder;

trait InteractsWithSeededRoles
{
    protected function seedRolesAndMenus(): void
    {
        $this->seed(RolesMenusSeeder::class);
    }

    protected function roleId(string $roleName): int
    {
        return (int) Role::where('role_name', $roleName)->value('id');
    }

    protected function userForRole(string $roleName): User
    {
        return User::factory()->create([
            'role_id' => $this->roleId($roleName),
            'is_active' => true,
        ]);
    }

    protected function actingAsRole(string $roleName): static
    {
        return $this->actingAs($this->userForRole($roleName));
    }
}
