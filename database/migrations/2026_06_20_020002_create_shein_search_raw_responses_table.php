<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors `amazon_search_raw_responses`. One row per SerpApi
 * google_shopping page so we can replay the parser later without
 * burning more SerpApi credits and so debugging keeps the full body
 * out of MySQL's `max_allowed_packet` ceiling.
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
