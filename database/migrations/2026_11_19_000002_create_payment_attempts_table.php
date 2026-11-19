<?php

use App\Models\PaymentAttempt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every Midtrans order id that was ever used for a payment. payments.midtrans_order_id stays the current one.
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('midtrans_order_id', 50)->unique();
            $table->string('method', 30)->nullable();
            $table->string('status', 20)->default('pending');
            $table->auditColumns();
        });

        $this->backfill();
    }

    /** One attempt for the Midtrans id every payment has now. Safe to run again. */
    public function backfill(): void
    {
        DB::table('payments')
            ->whereNotNull('midtrans_order_id')
            ->orderBy('id')
            ->chunkById(500, function ($payments) {
                foreach ($payments as $payment) {
                    PaymentAttempt::firstOrCreate(
                        ['midtrans_order_id' => $payment->midtrans_order_id],
                        [
                            'payment_id' => $payment->id,
                            'method' => $payment->payment_method,
                            'status' => $payment->payment_status,
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
