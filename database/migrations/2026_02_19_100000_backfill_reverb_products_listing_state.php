<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Backfill listing_state = 'live' where null so tab counts (All vs Active) work.
     * Run reverb:fetch to get real state from API afterwards.
     */
    public function up(): void
    {
        if (! Schema::hasTable('reverb_products') || ! Schema::hasColumn('reverb_products', 'listing_state')) {
            return;
        }
        DB::table('reverb_products')->whereNull('listing_state')->update(['listing_state' => 'live']);
    }

    public function down(): void
    {
        // No reverse – we don't re-null the column
    }
};
