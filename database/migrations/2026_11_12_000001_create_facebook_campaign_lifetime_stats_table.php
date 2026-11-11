<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lifetime (all-time) totals per Meta campaign, for the "Lifetime Acos" and
 * "Lifetime Sales" columns on /facebook-all-ads-sheet. Filled by
 * `php artisan facebook:sync-lifetime-stats` straight from Meta's insights
 * (date_preset=maximum) because the sheet itself is only an L30 export.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('facebook_campaign_lifetime_stats')) {
            return;
        }

        Schema::create('facebook_campaign_lifetime_stats', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_id', 40)->unique();
            $table->string('ad_account_id', 40)->nullable()->index();
            $table->decimal('spend', 15, 2)->default(0);
            $table->decimal('sales', 15, 2)->default(0);   // Meta purchase conversion value
            $table->unsignedBigInteger('sold')->default(0); // Meta purchases
            $table->unsignedBigInteger('impr')->default(0);
            $table->unsignedBigInteger('clk')->default(0);
            $table->date('first_day')->nullable();
            $table->date('last_day')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_campaign_lifetime_stats');
    }
};
