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
            $table->text('main_image')->nullable()->after('feature4');
            $table->text('main_image_brand')->nullable()->after('main_image');
            $table->text('image1')->nullable()->after('main_image_brand');
            $table->text('image2')->nullable()->after('image1');
            $table->text('image3')->nullable()->after('image2');
            $table->text('image4')->nullable()->after('image3');
            $table->text('image5')->nullable()->after('image4');
            $table->text('image6')->nullable()->after('image5');
            $table->text('image7')->nullable()->after('image6');
            $table->text('image8')->nullable()->after('image7');
            $table->text('image9')->nullable()->after('image8');
            $table->text('image10')->nullable()->after('image9');
            $table->text('image11')->nullable()->after('image10');
            $table->text('image12')->nullable()->after('image11');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            $table->dropColumn([
                'main_image', 'main_image_brand',
                'image1', 'image2', 'image3', 'image4', 'image5', 'image6',
                'image7', 'image8', 'image9', 'image10', 'image11', 'image12'
            ]);
        });
    }
};
