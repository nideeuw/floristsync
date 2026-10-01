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
            $table->renameColumn('created_at', 'created_on');
            $table->renameColumn('updated_at', 'modified_on');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->after('id')->constrained('roles')->restrictOnDelete();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('modified_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('modified_by');
            $table->dropColumn(['phone', 'address', 'is_active']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('created_on', 'created_at');
            $table->renameColumn('modified_on', 'updated_at');
        });
    }
};
