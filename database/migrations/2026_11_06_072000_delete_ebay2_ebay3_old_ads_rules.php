<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * eBay 2 / eBay 3 only use Ads Rule PMT now. Drop the saved settings of the old ads rules:
     * Dil vs SBid, the Dilution Rule colour bands, the legacy SCVR bid bands and Sbid (Views).
     */
    public function up(): void
    {
        if (! Schema::hasTable('ebay_sbid_rules')) {
            return;
        }

        DB::table('ebay_sbid_rules')->whereIn('key', [
            'ebay2', 'ebay3',
            'ebay2_dil', 'ebay3_dil',
            'ebay2_dil_sbid', 'ebay3_dil_sbid',
            'ebay2_sbid_views', 'ebay3_sbid_views',
        ])->delete();
    }

    public function down(): void
    {
        // The deleted settings are not restored.
    }
};
