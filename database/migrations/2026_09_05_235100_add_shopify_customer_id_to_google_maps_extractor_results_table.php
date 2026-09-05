<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_maps_extractor_results')) {
            return;
        }

        Schema::table('google_maps_extractor_results', function (Blueprint $table) {
            if (! Schema::hasColumn('google_maps_extractor_results', 'shopify_customer_id')) {
                $table->unsignedBigInteger('shopify_customer_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('google_maps_extractor_results')) {
            return;
        }

        Schema::table('google_maps_extractor_results', function (Blueprint $table) {
            if (Schema::hasColumn('google_maps_extractor_results', 'shopify_customer_id')) {
                $table->dropColumn('shopify_customer_id');
            }
        });
    }
};
