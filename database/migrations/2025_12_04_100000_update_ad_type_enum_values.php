<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update existing data to new format
        DB::statement("UPDATE meta_all_ads SET ad_type = 'Facebook Single Image' WHERE ad_type = 'Single Image'");
        DB::statement("UPDATE meta_all_ads SET ad_type = 'Facebook Single Video' WHERE ad_type = 'Single Video'");
        DB::statement("UPDATE meta_all_ads SET ad_type = 'Facebook Carousal' WHERE ad_type = 'Carousal'");
        DB::statement("UPDATE meta_all_ads SET ad_type = 'Facebook Existing Post' WHERE ad_type = 'Existing Post'");
        DB::statement("UPDATE meta_all_ads SET ad_type = 'Facebook Catalogue Ad' WHERE ad_type = 'Catalogue Ad'");
        
        // Change column type to enum with new values
        DB::statement("ALTER TABLE meta_all_ads MODIFY COLUMN ad_type ENUM(
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
        ) NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to old format
        DB::statement("UPDATE meta_all_ads SET ad_type = 'Single Image' WHERE ad_type = 'Facebook Single Image'");
        DB::statement("UPDATE meta_all_ads SET ad_type = 'Single Video' WHERE ad_type = 'Facebook Single Video'");
        DB::statement("UPDATE meta_all_ads SET ad_type = 'Carousal' WHERE ad_type = 'Facebook Carousal'");
        DB::statement("UPDATE meta_all_ads SET ad_type = 'Existing Post' WHERE ad_type = 'Facebook Existing Post'");
        DB::statement("UPDATE meta_all_ads SET ad_type = 'Catalogue Ad' WHERE ad_type = 'Facebook Catalogue Ad'");
        
        // Revert column type
        Schema::table('meta_all_ads', function (Blueprint $table) {
            $table->string('ad_type', 100)->nullable()->change();
        });
    }
};
