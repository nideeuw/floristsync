<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20); // webhook, admin, member, scheduler, pos, system
            $table->string('action', 60);
            $table->string('entity', 60);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip', 45)->nullable();
            $table->auditColumns();

            $table->index(['entity', 'entity_id']);
            $table->index('user_id');
            $table->index('action');
            $table->index('created_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
