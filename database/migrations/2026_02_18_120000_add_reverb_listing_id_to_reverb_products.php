<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reverb_products', function (Blueprint $table) {
            $table->string('reverb_listing_id', 64)->nullable()->after('sku');
        });
    }

    public function down(): void
    {
        Schema::table('reverb_products', function (Blueprint $table) {
            $table->dropColumn('reverb_listing_id');
        });
    }
};
