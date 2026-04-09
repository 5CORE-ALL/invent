<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSWGearExchangeListingStatusesTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sw_gear_exchange_listing_statuses')) {
            return;
        }

        Schema::create('sw_gear_exchange_listing_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sw_gear_exchange_listing_statuses');
    }
}