<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * mbid = manual bid (user-set via INC/DEC SBID). Stored in same table, no new table.
     */
    public function up(): void
    {
        Schema::table('google_ads_campaigns', function (Blueprint $table) {
            $table->decimal('mbid', 10, 2)->nullable()->after('budget_explicitly_shared');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_ads_campaigns', function (Blueprint $table) {
            $table->dropColumn('mbid');
        });
    }
};
