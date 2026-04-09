<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('shopify_skus')) {
            return;
        }
        if (Schema::hasColumn('shopify_skus', 'product_link')) {
            return;
        }

        Schema::table('shopify_skus', function (Blueprint $table) {
            $table->text('product_link')->nullable()->after('variant_title');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('shopify_skus')) {
            return;
        }
        if (! Schema::hasColumn('shopify_skus', 'product_link')) {
            return;
        }

        Schema::table('shopify_skus', function (Blueprint $table) {
            $table->dropColumn('product_link');
        });
    }
};
