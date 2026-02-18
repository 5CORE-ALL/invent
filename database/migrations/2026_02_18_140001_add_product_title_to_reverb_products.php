<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reverb_products', function (Blueprint $table) {
            $table->string('product_title', 512)->nullable()->after('listing_state')->comment('Cached from Shopify for search by name');
        });
    }

    public function down(): void
    {
        Schema::table('reverb_products', function (Blueprint $table) {
            $table->dropColumn('product_title');
        });
    }
};
