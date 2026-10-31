<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KW cousin links for the Keyword Targeting page (separate grouping from amazon_campaign_links).
 * Fully-connected mesh: each pair is stored in both directions.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('amazon_kw_cousin_links')) {
            return;
        }

        Schema::create('amazon_kw_cousin_links', function (Blueprint $table) {
            $table->id();
            $table->string('campaign', 512);
            $table->string('linked_campaign', 512);
            $table->string('campaign_norm', 191);
            $table->string('linked_campaign_norm', 191);
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['campaign_norm', 'linked_campaign_norm'], 'amz_kw_cousin_pair_unique');
            $table->index('campaign_norm', 'amz_kw_cousin_norm_idx');
            $table->index('linked_campaign_norm', 'amz_kw_cousin_linked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_kw_cousin_links');
    }
};
