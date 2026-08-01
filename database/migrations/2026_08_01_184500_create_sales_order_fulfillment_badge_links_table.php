<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_order_fulfillment_badge_links')) {
            return;
        }

        Schema::create('sales_order_fulfillment_badge_links', function (Blueprint $table) {
            $table->id();
            $table->string('badge_key', 40)->unique();
            $table->string('link', 1000)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_fulfillment_badge_links');
    }
};
