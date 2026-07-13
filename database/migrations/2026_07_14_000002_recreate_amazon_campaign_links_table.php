<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The initial create migration was skipped on environments where an orphan
 * `amazon_campaign_links` table (with different columns) already existed, leaving the
 * campaign-link feature pointing at the wrong schema. This drops any existing table and
 * recreates it with the correct columns. The table is only used by the campaign-link
 * feature (no other code references it), so dropping is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('amazon_campaign_links');

        Schema::create('amazon_campaign_links', function (Blueprint $table) {
            $table->id();
            $table->string('campaign', 512);
            $table->string('linked_campaign', 512);
            $table->string('campaign_norm', 512);
            $table->string('linked_campaign_norm', 512);
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['campaign_norm', 'linked_campaign_norm'], 'amz_campaign_link_pair_unique');
            $table->index('campaign_norm', 'amz_campaign_link_norm_idx');
            $table->index('linked_campaign_norm', 'amz_campaign_link_linked_norm_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_campaign_links');
    }
};
