<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add listing_status to amazon_datsheets if missing (original migration used wrong table name amazon_datasheets).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('amazon_datsheets', 'listing_status')) {
            Schema::table('amazon_datsheets', function (Blueprint $table) {
                $table->string('listing_status')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('amazon_datsheets', 'listing_status')) {
            Schema::table('amazon_datsheets', function (Blueprint $table) {
                $table->dropColumn('listing_status');
            });
        }
    }
};
