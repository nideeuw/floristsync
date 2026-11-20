<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->date('delivery_date')->nullable()->after('notes');
            $table->string('delivery_slot', 20)->nullable()->after('delivery_date');
            $table->string('card_message', 200)->nullable()->after('delivery_slot');
            $table->index('delivery_date');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['delivery_date']);
            $table->dropColumn(['delivery_date', 'delivery_slot', 'card_message']);
        });
    }
};
