<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            if (!Schema::hasColumn('product_master', 'is_spare_part')) {
                $table->boolean('is_spare_part')->default(false);
            }
            if (!Schema::hasColumn('product_master', 'min_stock_level')) {
                $table->unsignedInteger('min_stock_level')->nullable();
            }
            if (!Schema::hasColumn('product_master', 'reorder_level')) {
                $table->unsignedInteger('reorder_level')->nullable();
            }
            if (!Schema::hasColumn('product_master', 'max_stock_level')) {
                $table->unsignedInteger('max_stock_level')->nullable();
            }
            if (!Schema::hasColumn('product_master', 'lead_time_days')) {
                $table->unsignedInteger('lead_time_days')->nullable();
            }
            if (!Schema::hasColumn('product_master', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable();
            }
        });

        Schema::table('product_master', function (Blueprint $table) {
            if (Schema::hasColumn('product_master', 'parent_id')) {
                $table->foreign('parent_id')
                    ->references('id')
                    ->on('product_master')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            if (Schema::hasColumn('product_master', 'parent_id')) {
                $table->dropForeign(['parent_id']);
            }
        });

        Schema::table('product_master', function (Blueprint $table) {
            $cols = ['is_spare_part', 'min_stock_level', 'reorder_level', 'max_stock_level', 'lead_time_days', 'parent_id'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('product_master', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
