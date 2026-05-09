<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_catalog_products', function (Blueprint $table) {
            $table->longText('body_html')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shopify_catalog_products', function (Blueprint $table) {
            $table->text('body_html')->nullable()->change();
        });
    }
};
