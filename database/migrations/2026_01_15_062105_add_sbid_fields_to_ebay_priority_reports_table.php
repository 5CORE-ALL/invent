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
        Schema::table('ebay_priority_reports', function (Blueprint $table) {
            $table->string('last_sbid')->nullable()->after('cost_per_click');
            $table->string('sbid_m')->nullable()->after('last_sbid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ebay_priority_reports', function (Blueprint $table) {
            $table->dropColumn(['last_sbid', 'sbid_m']);
        });
    }
};
