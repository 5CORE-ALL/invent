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
        if (! Schema::hasTable('doba_metrics')) {
            return;
        }

        if (! Schema::hasColumn('doba_metrics', 'quantity_l7')) {
            Schema::table('doba_metrics', function (Blueprint $table) {
                $table->integer('quantity_l7')->default(0)->after('quantity_l60');
            });
        }
        if (! Schema::hasColumn('doba_metrics', 'quantity_l7_prev')) {
            Schema::table('doba_metrics', function (Blueprint $table) {
                $table->integer('quantity_l7_prev')->default(0)->after('quantity_l7');
            });
        }
        if (! Schema::hasColumn('doba_metrics', 'order_count_l7')) {
            Schema::table('doba_metrics', function (Blueprint $table) {
                $table->integer('order_count_l7')->default(0)->after('order_count_l60');
            });
        }
        if (! Schema::hasColumn('doba_metrics', 'order_count_l7_prev')) {
            Schema::table('doba_metrics', function (Blueprint $table) {
                $table->integer('order_count_l7_prev')->default(0)->after('order_count_l7');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('doba_metrics')) {
            return;
        }

        $columns = ['quantity_l7', 'quantity_l7_prev', 'order_count_l7', 'order_count_l7_prev'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('doba_metrics', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('doba_metrics', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
