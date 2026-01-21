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
            $table->integer('quantity_l7')->default(0)->after('quantity_l60');
            $table->integer('quantity_l7_prev')->default(0)->after('quantity_l7');
            $table->integer('order_count_l7')->default(0)->after('order_count_l60');
            $table->integer('order_count_l7_prev')->default(0)->after('order_count_l7');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('doba_metrics', function (Blueprint $table) {
            $table->dropColumn(['quantity_l7', 'quantity_l7_prev', 'order_count_l7', 'order_count_l7_prev']);
        });
    }
};
