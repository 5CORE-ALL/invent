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
        Schema::table('inventories', function (Blueprint $table) {
            $table->integer('available_qty')->nullable()->after('on_hand');
            $table->string('shopify_variant_id')->nullable()->after('available_qty');
            $table->string('shopify_inventory_item_id')->nullable()->after('shopify_variant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn(['available_qty', 'shopify_variant_id', 'shopify_inventory_item_id']);
        });
    }
};
