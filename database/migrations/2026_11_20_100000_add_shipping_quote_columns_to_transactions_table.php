<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // RajaOngkir quote inputs, so a shipping quote can be repeated and audited.
            $table->unsignedBigInteger('destination_id')->nullable()->after('shipping_address');
            $table->unsignedBigInteger('shipping_origin_id')->nullable()->after('destination_id');
            // Receiver phone as entered at checkout; tracking uses its last 5 digits.
            $table->string('shipping_phone', 20)->nullable()->after('shipping_origin_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['destination_id', 'shipping_origin_id', 'shipping_phone']);
        });
    }
};
