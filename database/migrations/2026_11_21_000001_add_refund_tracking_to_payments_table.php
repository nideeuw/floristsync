<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('refund_status', 20)->nullable(); // pending, success, failed
            $table->unsignedBigInteger('refund_amount')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_key', 100)->nullable();
            $table->index('refund_status');
        });

        // Refunds that were made before this column existed.
        DB::table('payments')
            ->whereIn('payment_status', ['refund', 'partial_refund'])
            ->whereNull('refund_status')
            ->update([
                'refund_status' => 'success',
                'refund_amount' => DB::raw("CASE WHEN payment_status = 'refund' THEN amount ELSE NULL END"),
                'refunded_at' => DB::raw('COALESCE(modified_on, created_on)'),
                'refund_key' => DB::raw("'RF-' || midtrans_order_id"),
            ]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['refund_status']);
            $table->dropColumn(['refund_status', 'refund_amount', 'refunded_at', 'refund_key']);
        });
    }
};
