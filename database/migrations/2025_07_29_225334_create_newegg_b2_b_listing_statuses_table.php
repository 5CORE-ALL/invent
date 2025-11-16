<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNeweggB2BListingStatusesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('newegg_b2b_listing_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->json('value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('newegg_b2b_listing_statuses');
    }
}
