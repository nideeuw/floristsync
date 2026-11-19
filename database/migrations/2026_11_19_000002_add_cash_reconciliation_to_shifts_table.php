<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->bigInteger('opening_cash')->nullable()->default(0);
            $table->bigInteger('closing_cash_counted')->nullable();
            $table->bigInteger('cash_difference')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shifts', function (Blueprint $table) {
            $table->dropColumn(['opening_cash', 'closing_cash_counted', 'cash_difference']);
        });
    }
};
