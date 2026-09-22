<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_shopping_live_syncs')) {
            return;
        }

        Schema::table('google_shopping_live_syncs', function (Blueprint $table) {
            if (! Schema::hasColumn('google_shopping_live_syncs', 'bid_green')) {
                $table->boolean('bid_green')->default(false)->after('bid_fetched_at');
            }
            if (! Schema::hasColumn('google_shopping_live_syncs', 'bgt_green')) {
                $table->boolean('bgt_green')->default(false)->after('bgt_fetched_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('google_shopping_live_syncs')) {
            return;
        }

        Schema::table('google_shopping_live_syncs', function (Blueprint $table) {
            if (Schema::hasColumn('google_shopping_live_syncs', 'bid_green')) {
                $table->dropColumn('bid_green');
            }
            if (Schema::hasColumn('google_shopping_live_syncs', 'bgt_green')) {
                $table->dropColumn('bgt_green');
            }
        });
    }
};
