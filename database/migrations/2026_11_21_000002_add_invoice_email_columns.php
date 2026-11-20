<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Set when the invoice email of a paid order was sent. It is what keeps the email to one per order.
            $table->timestamp('invoice_emailed_at')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_invoice')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('email_invoice');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('invoice_emailed_at');
        });
    }
};
