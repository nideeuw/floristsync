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
        Schema::table('users', function (Blueprint $table) {
            $table->string('postal_code', 10)->nullable()->after('address');
            $table->unsignedBigInteger('destination_id')->nullable()->after('postal_code');
            $table->string('destination_label')->nullable()->after('destination_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['postal_code', 'destination_id', 'destination_label']);
        });
    }
};
