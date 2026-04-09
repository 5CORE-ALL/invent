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
        if (! Schema::hasTable('product_master')) {
            return;
        }

        $after = ['feature1' => 'product_description', 'feature2' => 'feature1', 'feature3' => 'feature2', 'feature4' => 'feature3'];
        foreach (['feature1', 'feature2', 'feature3', 'feature4'] as $col) {
            if (Schema::hasColumn('product_master', $col)) {
                continue;
            }
            $prev = $after[$col];
            Schema::table('product_master', function (Blueprint $table) use ($col, $prev) {
                $table->string($col, 100)->nullable()->after($prev);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('product_master')) {
            return;
        }

        $columns = ['feature1', 'feature2', 'feature3', 'feature4'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('product_master', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
