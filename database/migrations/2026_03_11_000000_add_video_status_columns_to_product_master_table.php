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
        Schema::table('product_master', function (Blueprint $table) {
            $table->string('video_product_overview_status', 50)->nullable()->after('video_product_overview');
            $table->string('video_unboxing_status', 50)->nullable()->after('video_unboxing');
            $table->string('video_how_to_status', 50)->nullable()->after('video_how_to');
            $table->string('video_setup_status', 50)->nullable()->after('video_setup');
            $table->string('video_troubleshooting_status', 50)->nullable()->after('video_troubleshooting');
            $table->string('video_brand_story_status', 50)->nullable()->after('video_brand_story');
            $table->string('video_product_benefits_status', 50)->nullable()->after('video_product_benefits');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            $table->dropColumn([
                'video_product_overview_status',
                'video_unboxing_status',
                'video_how_to_status',
                'video_setup_status',
                'video_troubleshooting_status',
                'video_brand_story_status',
                'video_product_benefits_status',
            ]);
        });
    }
};
