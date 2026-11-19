<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * views_prior = product landing-page sessions of the 30 days before the L30 window (days 31-60 ago).
     * Used for LT O CVR on /shopify-b2c-pricing: (units L30 + L60) / (views + views_prior).
     */
    public function up(): void
    {
        if (! Schema::hasColumn('shopify_skus', 'views_prior')) {
            Schema::table('shopify_skus', function (Blueprint $table) {
                $table->unsignedInteger('views_prior')->nullable()->after('views');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('shopify_skus', 'views_prior')) {
            Schema::table('shopify_skus', function (Blueprint $table) {
                $table->dropColumn('views_prior');
            });
        }
    }
};
