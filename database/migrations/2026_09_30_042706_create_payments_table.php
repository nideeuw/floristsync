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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->string('midtrans_order_id', 50)->nullable()->unique();
            $table->string('payment_method', 30)->nullable();
            $table->string('snap_token')->nullable();
            $table->string('payment_status', 20)->default('pending');
            $table->string('payment_proof')->nullable();
            $table->unsignedBigInteger('amount');
            $table->timestamp('payment_date')->nullable();
            $table->auditColumns();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
