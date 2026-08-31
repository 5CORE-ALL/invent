<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('newegg_listing_views')) {
            return;
        }

        Schema::create('newegg_listing_views', function (Blueprint $table) {
            $table->id();
            $table->string('seller_part_number', 191)->nullable()->index();
            $table->string('item_number', 80)->nullable()->index();
            $table->string('title', 500)->nullable();
            $table->unsignedInteger('sbn_inventory')->nullable();
            $table->unsignedInteger('sbs_inventory')->nullable();
            $table->unsignedInteger('sessions')->nullable();
            $table->decimal('session_pct', 8, 2)->nullable();
            $table->unsignedInteger('page_views')->nullable();
            $table->decimal('page_view_pct', 8, 2)->nullable();
            $table->unsignedInteger('orders_sold')->nullable();
            $table->decimal('sales', 12, 2)->nullable();
            $table->unsignedInteger('units_sold')->nullable();
            $table->decimal('unit_session_pct', 8, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newegg_listing_views');
    }
};
