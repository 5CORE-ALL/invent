<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Amazon SP campaign links — a fully-connected mesh of campaign-name edges (same model as
     * lmp_sku_links). Linked campaigns form a group so keywords can later be pushed across the
     * whole group. Stored bidirectionally; groups are the connected components of the edges.
     */
    public function up(): void
    {
        if (Schema::hasTable('amazon_campaign_links')) {
            return;
        }

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
