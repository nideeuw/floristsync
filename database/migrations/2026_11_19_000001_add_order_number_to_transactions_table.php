<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Stable number the member and the staff see. Midtrans ids live in payment_attempts.
            $table->string('order_number', 40)->nullable()->unique();
        });

        $this->backfill();
    }

    /** Gives every old row its number. Safe to run again: only rows without a number are changed. */
    public function backfill(): void
    {
        DB::table('transactions')
            ->whereNull('order_number')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $padded = str_pad((string) $row->id, 6, '0', STR_PAD_LEFT);

                    $number = $row->transaction_type === 'pos'
                        ? 'POS-'.$padded
                        : 'FS-'.Carbon::parse($row->created_on ?? now())->format('Ymd').'-'.$padded;

                    DB::table('transactions')->where('id', $row->id)->update(['order_number' => $number]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['order_number']);
            $table->dropColumn('order_number');
        });
    }
};
