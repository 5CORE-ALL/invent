<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * NOTE: This migration has been superseded by 2025_12_04_100000_update_ad_type_enum_values.php
     * which updates the enum to include Facebook and Instagram specific ad types.
     */
    public function up(): void
    {
        Schema::table('meta_all_ads', function (Blueprint $table) {
            $table->enum('ad_type', [
                'Facebook Single Image',
                'Facebook Single Video',
                'Facebook Carousal',
                'Facebook Existing Post',
                'Facebook Catalogue Ad',
                'Instagram Single Image',
                'Instagram Single Video',
                'Instagram Carousal',
                'Instagram Existing Post',
                'Instagram Catalogue Ad'
            ])->nullable()->after('campaign_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meta_all_ads', function (Blueprint $table) {
            $table->dropColumn('ad_type');
        });
    }
};
