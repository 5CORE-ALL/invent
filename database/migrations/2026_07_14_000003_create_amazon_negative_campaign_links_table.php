<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaign links for negative-keyword sharing (separate grouping from the keyword links in
 * amazon_campaign_links). Same fully-connected mesh structure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('amazon_negative_campaign_links');

        Schema::create('amazon_negative_campaign_links', function (Blueprint $table) {
            $table->id();
            $table->string('campaign', 512);
            $table->string('linked_campaign', 512);
            $table->string('campaign_norm', 191);
            $table->string('linked_campaign_norm', 191);
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['campaign_norm', 'linked_campaign_norm'], 'amz_neg_link_pair_unique');
            $table->index('campaign_norm', 'amz_neg_link_norm_idx');
            $table->index('linked_campaign_norm', 'amz_neg_link_linked_norm_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_negative_campaign_links');
    }
};
