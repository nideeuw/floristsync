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
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('cashier_id')->constrained('shifts')->nullOnDelete();
            $table->text('notes')->nullable()->after('tracking_number');
            $table->string('cancel_reason', 255)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropConstrainedForeignId('shift_id');
            $table->dropColumn(['notes', 'cancel_reason', 'cancelled_at']);
        });
    }
};
