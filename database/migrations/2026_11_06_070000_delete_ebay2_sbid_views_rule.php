<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** eBay 2 no longer has the Sbid (Views) rule: drop its saved settings. */
    public function up(): void
    {
        if (Schema::hasTable('ebay_sbid_rules')) {
            DB::table('ebay_sbid_rules')->where('key', 'ebay2_sbid_views')->delete();
        }
    }

    public function down(): void
    {
        // The deleted settings are not restored.
    }
};
