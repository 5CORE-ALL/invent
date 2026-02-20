<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sync tracking: last synced at and last known Shopify qty for change detection.
     */
    public function up(): void
    {
        Schema::table('reverb_products', function (Blueprint $table) {
            $table->timestamp('last_synced_at')->nullable();
            $table->integer('last_shopify_qty')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reverb_products', function (Blueprint $table) {
            $table->dropColumn(['last_synced_at', 'last_shopify_qty']);
        });
    }
};
