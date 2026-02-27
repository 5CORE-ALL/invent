<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $tableName = 'amazon_listings_raw';
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (! Schema::hasColumn($tableName, 'thumbnail_image')) {
                $table->string('thumbnail_image')->nullable()->after('asin1');
            }
        });
    }

    public function down(): void
    {
        $tableName = 'amazon_listings_raw';
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'thumbnail_image')) {
                $table->dropColumn('thumbnail_image');
            }
        });
    }
};

