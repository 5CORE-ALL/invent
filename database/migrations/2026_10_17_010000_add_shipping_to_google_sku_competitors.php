<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_sku_competitors') || Schema::hasColumn('google_sku_competitors', 'shipping')) {
            return;
        }
        Schema::table('google_sku_competitors', function (Blueprint $table) {
            $table->decimal('shipping', 10, 2)->default(0)->after('price');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('google_sku_competitors') || ! Schema::hasColumn('google_sku_competitors', 'shipping')) {
            return;
        }
        Schema::table('google_sku_competitors', function (Blueprint $table) {
            $table->dropColumn('shipping');
        });
    }
};
