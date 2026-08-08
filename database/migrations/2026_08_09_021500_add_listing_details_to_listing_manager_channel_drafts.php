<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('listing_manager_channel_drafts')) {
            return;
        }

        Schema::table('listing_manager_channel_drafts', function (Blueprint $table) {
            if (! Schema::hasColumn('listing_manager_channel_drafts', 'listing_details')) {
                $table->json('listing_details')->nullable()->after('amazon_snapshot');
            }
            if (! Schema::hasColumn('listing_manager_channel_drafts', 'external_listing_id')) {
                $table->string('external_listing_id', 64)->nullable()->index()->after('status');
            }
            if (! Schema::hasColumn('listing_manager_channel_drafts', 'publish_checked_at')) {
                $table->timestamp('publish_checked_at')->nullable()->after('listed_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('listing_manager_channel_drafts')) {
            return;
        }

        Schema::table('listing_manager_channel_drafts', function (Blueprint $table) {
            if (Schema::hasColumn('listing_manager_channel_drafts', 'publish_checked_at')) {
                $table->dropColumn('publish_checked_at');
            }
            if (Schema::hasColumn('listing_manager_channel_drafts', 'external_listing_id')) {
                $table->dropColumn('external_listing_id');
            }
            if (Schema::hasColumn('listing_manager_channel_drafts', 'listing_details')) {
                $table->dropColumn('listing_details');
            }
        });
    }
};
