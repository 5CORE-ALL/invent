<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-target flags for the Keyword Targeting page's targets modal.
     *  - deleted: the target is hidden and never re-imported (reports / Amazon pull / KW-cousin sync skip it)
     *  - ignored: the target stays listed but is skipped when keywords are pushed to Amazon
     * One row per (kind, campaign, keyword, match type); status changes in place.
     */
    public function up(): void
    {
        if (Schema::hasTable('amazon_kw_targeting_flags')) {
            return;
        }

        Schema::create('amazon_kw_targeting_flags', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 8); // kw | neg
            $table->string('campaign_id', 64);
            $table->string('keyword_key', 255);
            $table->string('match_type', 32)->default('');
            $table->string('label_lc', 320); // lower-cased "keyword (match)" label, used to exclude from report counts
            $table->string('status', 12); // deleted | ignored
            $table->string('flagged_by', 191)->nullable();
            $table->timestamps();

            $table->unique(['kind', 'campaign_id', 'keyword_key', 'match_type'], 'amz_kw_flag_unique');
            $table->index(['kind', 'status', 'campaign_id'], 'amz_kw_flag_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_kw_targeting_flags');
    }
};
