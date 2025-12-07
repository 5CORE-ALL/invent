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
        Schema::table('meta_all_ads', function (Blueprint $table) {
            // Add group_id column with foreign key
            $table->unsignedBigInteger('group_id')->nullable()->after('campaign_id');
            $table->foreign('group_id')->references('id')->on('meta_ad_groups')->onDelete('set null');
            
            // Drop ad_type column if exists
            if (Schema::hasColumn('meta_all_ads', 'ad_type')) {
                $table->dropColumn('ad_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meta_all_ads', function (Blueprint $table) {
            // Drop foreign key and group_id column
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
            
            // Re-add ad_type column
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
                'Instagram Catalogue Ad',
            ])->nullable()->after('campaign_id');
        });
    }
};
