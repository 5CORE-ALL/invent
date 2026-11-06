<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** /ebay2/campaign-ads no longer has the Dilution Rule (DIL % -> Color): drop its saved bands. */
    public function up(): void
    {
        if (Schema::hasTable('ebay_sbid_rules')) {
            DB::table('ebay_sbid_rules')->where('key', 'ebay2_dil')->delete();
        }
    }

    public function down(): void
    {
        // The deleted bands are not restored.
    }
};
