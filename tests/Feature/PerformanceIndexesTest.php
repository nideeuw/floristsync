<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolesMenusSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PerformanceIndexesTest extends TestCase
{
    use RefreshDatabase;

    public function test_performance_indexes_exist(): void
    {
        $expected = [
            'transactions' => ['transaction_type', 'status', 'created_on', 'user_id'],
            'stocks' => ['product_id', 'date'],
            'notifications' => ['user_id', 'is_read'],
            'products' => ['is_active', 'category_id'],
            'cart_items' => ['user_id'],
        ];

        foreach ($expected as $table => $columns) {
            foreach ($columns as $column) {
                $this->assertTrue(
                    Schema::hasIndex($table, [$column]),
                    "Missing index on {$table}.{$column}"
                );
            }
        }
    }

    public function test_track_list_query_count_does_not_grow_with_rows(): void
    {
        $this->seed(RolesMenusSeeder::class);

        $memberRole = Role::where('role_name', Role::MEMBER)->firstOrFail();

        $member = User::factory()->create([
            'role_id' => $memberRole->id,
            'is_active' => true,
        ]);

        $this->createOrders($member, 3);

        // Warm-up: session, cache and menu-permission rows settle on the first request.
        $this->actingAs($member)->get(route('track'))->assertOk();

        $withThree = $this->countTrackQueries($member);

        $this->createOrders($member, 12);

        $withFifteen = $this->countTrackQueries($member);

        $this->assertSame(
            $withThree,
            $withFifteen,
            "Track list used {$withThree} queries for 3 orders but {$withFifteen} for 15 — a per-row query (N+1) is leaking."
        );
    }

    private function countTrackQueries(User $member): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($member)->get(route('track'))->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    private function createOrders(User $member, int $count): void
    {
        $transactions = Transaction::factory()->count($count)->create([
            'user_id' => $member->id,
            'transaction_type' => Transaction::TYPE_ONLINE,
            'status' => Transaction::PROCESSING,
        ]);

        foreach ($transactions as $transaction) {
            Payment::factory()->create([
                'transaction_id' => $transaction->id,
                'payment_status' => 'settlement',
                'payment_date' => now(),
            ]);
        }
    }
}
