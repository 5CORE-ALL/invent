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

        $after = [
            'main_image' => 'feature4',
            'main_image_brand' => 'main_image',
            'image1' => 'main_image_brand',
            'image2' => 'image1',
            'image3' => 'image2',
            'image4' => 'image3',
            'image5' => 'image4',
            'image6' => 'image5',
            'image7' => 'image6',
            'image8' => 'image7',
            'image9' => 'image8',
            'image10' => 'image9',
            'image11' => 'image10',
            'image12' => 'image11',
        ];
        foreach (array_keys($after) as $col) {
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

        $columns = [
            'main_image', 'main_image_brand',
            'image1', 'image2', 'image3', 'image4', 'image5', 'image6',
            'image7', 'image8', 'image9', 'image10', 'image11', 'image12',
        ];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('product_master', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
