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
            'video_product_overview_status' => 'video_product_overview',
            'video_unboxing_status' => 'video_unboxing',
            'video_how_to_status' => 'video_how_to',
            'video_setup_status' => 'video_setup',
            'video_troubleshooting_status' => 'video_troubleshooting',
            'video_brand_story_status' => 'video_brand_story',
            'video_product_benefits_status' => 'video_product_benefits',
        ];
        foreach (array_keys($after) as $col) {
            if (Schema::hasColumn('product_master', $col)) {
                continue;
            }
            $prev = $after[$col];
            Schema::table('product_master', function (Blueprint $table) use ($col, $prev) {
                $table->string($col, 50)->nullable()->after($prev);
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
            'video_product_overview_status',
            'video_unboxing_status',
            'video_how_to_status',
            'video_setup_status',
            'video_troubleshooting_status',
            'video_brand_story_status',
            'video_product_benefits_status',
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
