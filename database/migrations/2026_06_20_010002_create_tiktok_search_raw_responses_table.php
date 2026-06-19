<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tiktok_search_raw_responses')) {
            return;
        }

        Schema::create('tiktok_search_raw_responses', function (Blueprint $table) {
            $table->id();
            $table->string('search_query')->index();
            $table->string('marketplace', 50)->nullable();
            $table->string('region', 8)->nullable();
            $table->string('provider', 50)->default('apify');
            // Provider's actor / dataset / run id so we can re-pull later if needed.
            $table->string('provider_run_id')->nullable()->index();
            $table->unsignedInteger('items_count')->nullable();
            $table->longText('raw_response');
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_search_raw_responses');
    }
};
