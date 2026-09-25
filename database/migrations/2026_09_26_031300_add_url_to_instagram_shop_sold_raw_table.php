<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('instagram_shop_sold_raw') || Schema::hasColumn('instagram_shop_sold_raw', 'url')) {
            return;
        }

        Schema::table('instagram_shop_sold_raw', function (Blueprint $table) {
            $table->string('url', 500)->nullable()->after('sku');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('instagram_shop_sold_raw') || ! Schema::hasColumn('instagram_shop_sold_raw', 'url')) {
            return;
        }

        Schema::table('instagram_shop_sold_raw', function (Blueprint $table) {
            $table->dropColumn('url');
        });
    }
};
