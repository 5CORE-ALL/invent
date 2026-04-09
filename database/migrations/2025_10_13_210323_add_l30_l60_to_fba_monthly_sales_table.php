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
        if (! Schema::hasTable('fba_monthly_sales')) {
            return;
        }

        if (! Schema::hasColumn('fba_monthly_sales', 'l30_units')) {
            Schema::table('fba_monthly_sales', function (Blueprint $table) {
                $table->integer('l30_units')->default(0);
            });
        }
        if (! Schema::hasColumn('fba_monthly_sales', 'l30_revenue')) {
            Schema::table('fba_monthly_sales', function (Blueprint $table) {
                $table->decimal('l30_revenue', 10, 2)->nullable();
            });
        }
        if (! Schema::hasColumn('fba_monthly_sales', 'l60_units')) {
            Schema::table('fba_monthly_sales', function (Blueprint $table) {
                $table->integer('l60_units')->default(0);
            });
        }
        if (! Schema::hasColumn('fba_monthly_sales', 'l60_revenue')) {
            Schema::table('fba_monthly_sales', function (Blueprint $table) {
                $table->decimal('l60_revenue', 10, 2)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('fba_monthly_sales')) {
            return;
        }

        $columns = ['l30_units', 'l30_revenue', 'l60_units', 'l60_revenue'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('fba_monthly_sales', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('fba_monthly_sales', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
