<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amazon_listings_raw', function (Blueprint $table) {
            if (! Schema::hasColumn('amazon_listings_raw', 'condition_type')) {
                $table->string('condition_type', 20)->nullable()->after('asin1');
            }
            if (! Schema::hasColumn('amazon_listings_raw', 'condition_type_display')) {
                $table->string('condition_type_display', 50)->nullable()->after('condition_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('amazon_listings_raw', function (Blueprint $table) {
            if (Schema::hasColumn('amazon_listings_raw', 'condition_type')) {
                $table->dropColumn('condition_type');
            }
            if (Schema::hasColumn('amazon_listings_raw', 'condition_type_display')) {
                $table->dropColumn('condition_type_display');
            }
        });
    }
};
