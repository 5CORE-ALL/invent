<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors `amazon_search_raw_responses`. One row per Apify Shein actor
 * run (full dataset body in `raw_response`, longText so we don't bump
 * into MySQL's `max_allowed_packet` ceiling). Lets us replay the
 * parser without spending more Apify credits, and gives the UI's
 * "View Raw Response" modal something to render. `page` / `pages_count`
 * are kept for parity with the Amazon table but the Shein flow only
 * persists one row per search today.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shein_search_raw_responses')) {
            return;
        }

        Schema::create('shein_search_raw_responses', function (Blueprint $table) {
            $table->id();
            $table->string('search_query')->index();
            $table->string('marketplace', 50)->nullable();
            $table->unsignedSmallInteger('page')->nullable();
            $table->longText('raw_response');
            $table->unsignedSmallInteger('pages_count')->nullable();
            $table->timestamps();

            $table->index(['search_query', 'page']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shein_search_raw_responses');
    }
};
