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
            $table->string('video_product_overview', 500)->nullable();
            $table->string('video_unboxing', 500)->nullable();
            $table->string('video_how_to', 500)->nullable();
            $table->string('video_setup', 500)->nullable();
            $table->string('video_troubleshooting', 500)->nullable();
            $table->string('video_brand_story', 500)->nullable();
            $table->string('video_product_benefits', 500)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            $table->dropColumn([
                'video_product_overview',
                'video_unboxing',
                'video_how_to',
                'video_setup',
                'video_troubleshooting',
                'video_brand_story',
                'video_product_benefits'
            ]);
        });
    }
};
