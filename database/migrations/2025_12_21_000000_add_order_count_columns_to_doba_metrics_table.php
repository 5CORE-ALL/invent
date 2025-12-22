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
        Schema::table('doba_metrics', function (Blueprint $table) {
            $table->integer('order_count_l30')->nullable()->after('quantity_l60');
            $table->integer('order_count_l60')->nullable()->after('order_count_l30');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doba_metrics', function (Blueprint $table) {
            $table->dropColumn(['order_count_l30', 'order_count_l60']);
        });
    }
};