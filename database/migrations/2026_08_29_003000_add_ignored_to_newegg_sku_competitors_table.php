<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('newegg_sku_competitors') || Schema::hasColumn('newegg_sku_competitors', 'ignored')) {
            return;
        }
        Schema::table('newegg_sku_competitors', function (Blueprint $table) {
            $table->boolean('ignored')->default(false)->after('shipping_cost');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('newegg_sku_competitors') || ! Schema::hasColumn('newegg_sku_competitors', 'ignored')) {
            return;
        }
        Schema::table('newegg_sku_competitors', function (Blueprint $table) {
            $table->dropColumn('ignored');
        });
    }
};
