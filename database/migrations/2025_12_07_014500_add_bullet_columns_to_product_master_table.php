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

        $after = ['bullet1' => 'title60', 'bullet2' => 'bullet1', 'bullet3' => 'bullet2', 'bullet4' => 'bullet3', 'bullet5' => 'bullet4'];
        foreach (['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5'] as $col) {
            if (Schema::hasColumn('product_master', $col)) {
                continue;
            }
            $prev = $after[$col];
            Schema::table('product_master', function (Blueprint $table) use ($col, $prev) {
                $table->text($col)->nullable()->after($prev);
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

        $columns = ['bullet1', 'bullet2', 'bullet3', 'bullet4', 'bullet5'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('product_master', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
