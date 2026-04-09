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

        $columns = [
            'video_product_overview',
            'video_unboxing',
            'video_how_to',
            'video_setup',
            'video_troubleshooting',
            'video_brand_story',
            'video_product_benefits',
        ];
        foreach ($columns as $col) {
            if (Schema::hasColumn('product_master', $col)) {
                continue;
            }
            Schema::table('product_master', function (Blueprint $table) use ($col) {
                $table->string($col, 500)->nullable();
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
            'video_product_overview',
            'video_unboxing',
            'video_how_to',
            'video_setup',
            'video_troubleshooting',
            'video_brand_story',
            'video_product_benefits',
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
