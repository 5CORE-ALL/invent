<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('walmart_campaign_reports', function (Blueprint $table) {
            $table->decimal('sales', 10, 2)->nullable()->after('spend');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('walmart_campaign_reports', function (Blueprint $table) {
            $table->dropColumn('sales');
        });
    }
};
