<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = collect([
            Role::ADMIN   => 'Manages products, stock, and orders',
            Role::CASHIER => 'Handles POS transactions and online orders',
            Role::OWNER   => 'Monitors revenue and reports',
            Role::MEMBER  => 'Registered customer',
        ])->map(fn ($description, $name) => Role::create([
            'role_name' => $name,
            'description' => $description,
        ]));

        foreach ([Role::ADMIN, Role::CASHIER, Role::OWNER] as $role) {
            User::create([
                'role_id' => $roles[$role]->id,
                'name' => ucfirst($role),
                'email' => "{$role}@floristsync.test",
                'password' => Hash::make('password'),
            ]);
        }

        $this->call(DummyDataSeeder::class);
    }
}
