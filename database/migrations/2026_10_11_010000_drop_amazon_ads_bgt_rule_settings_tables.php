<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Budget rules now live in amazon_ads_rules (one row per key).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('amazon_acos_sbgt_rule_settings');
        Schema::dropIfExists('amazon_ads_bgt_views_rule_settings');
        Schema::dropIfExists('amazon_ads_bgt_cvr_rule_settings');
        Schema::dropIfExists('amazon_ads_bgt_prc_rule_settings');
        Schema::dropIfExists('amazon_ads_bgt_reviews_rule_settings');
        Schema::dropIfExists('amazon_ads_bgt_dil_rule_settings');
        Schema::dropIfExists('amazon_ads_bgt_inv_rule_settings');
        Schema::dropIfExists('amazon_ads_bgt_spend_rule_settings');
    }

    public function down(): void
    {
        // Old per-rule tables are not recreated.
    }
};
