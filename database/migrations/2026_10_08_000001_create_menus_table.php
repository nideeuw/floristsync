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
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('menu_name', 100);
            $table->foreignId('parent_id')->nullable()->constrained('menus')->nullOnDelete();
            $table->unsignedTinyInteger('order_no')->default(0);
            $table->unsignedTinyInteger('menu_level')->default(1);
            $table->string('menu_behavior', 30)->nullable();
            $table->string('module_group', 30)->index();
            $table->string('menu_mode', 20)->nullable();
            $table->string('menu_icon', 100)->nullable();
            $table->string('url_link', 200)->nullable();
            $table->auditColumns();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
