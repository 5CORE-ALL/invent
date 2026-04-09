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

        if (! Schema::hasColumn('doba_metrics', 'order_count_l30')) {
            Schema::table('doba_metrics', function (Blueprint $table) {
                $table->integer('order_count_l30')->nullable()->after('quantity_l60');
            });
        }
        if (! Schema::hasColumn('doba_metrics', 'order_count_l60')) {
            Schema::table('doba_metrics', function (Blueprint $table) {
                $table->integer('order_count_l60')->nullable()->after('order_count_l30');
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

        $columns = ['order_count_l30', 'order_count_l60'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('doba_metrics', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('doba_metrics', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};