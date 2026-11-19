<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->indexes() as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasIndex($table, [$column])) {
                    Schema::table($table, fn (Blueprint $t) => $t->index($column));
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes() as $table => $columns) {
            foreach ($columns as $column) {
                if (Schema::hasIndex($table, [$column])) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropIndex([$column]));
                }
            }
        }
    }

    private function indexes(): array
    {
        return [
            'transactions' => ['transaction_type', 'created_on', 'user_id'],
            'stocks' => ['product_id', 'date'],
            'notifications' => ['user_id', 'is_read'],
            'products' => ['is_active', 'category_id'],
            'cart_items' => ['user_id'],
        ];
    }
};
