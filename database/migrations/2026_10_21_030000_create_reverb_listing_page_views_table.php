<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily snapshot of Reverb's lifetime listing page-view counter (stats.views) per SKU.
     * Day-over-day change of this counter is the real one-day views for Y Views / Y CVR
     * (the bump "views" are a rolling L30 impressions total and cannot give a daily number).
     */
    public function up(): void
    {
        if (Schema::hasTable('reverb_listing_page_views')) {
            return;
        }

        Schema::create('reverb_listing_page_views', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191);
            $table->string('listing_id', 64)->nullable();
            $table->date('record_date');
            $table->unsignedBigInteger('page_views')->default(0);
            $table->unsignedInteger('watches')->nullable();
            $table->timestamps();

            $table->unique(['sku', 'record_date']);
            $table->index('record_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reverb_listing_page_views');
    }
};
