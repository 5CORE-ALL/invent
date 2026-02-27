<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('amazon_listing_raws', function (Blueprint $table) {
            if (! Schema::hasColumn('amazon_listing_raws', 'thumbnail_image')) {
                $table->string('thumbnail_image')->nullable()->after('asin1');
            }
        });
    }

    public function down(): void
    {
        Schema::table('amazon_listing_raws', function (Blueprint $table) {
            if (Schema::hasColumn('amazon_listing_raws', 'thumbnail_image')) {
                $table->dropColumn('thumbnail_image');
            }
        });
    }
};

