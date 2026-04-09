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
        if (! Schema::hasTable('amazon_datsheets')) {
            return;
        }
        if (Schema::hasColumn('amazon_datsheets', 'listing_status')) {
            return;
        }

        Schema::table('amazon_datsheets', function (Blueprint $table) {
            $table->string('listing_status')->nullable()->after('sold');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('amazon_datsheets')) {
            return;
        }
        if (! Schema::hasColumn('amazon_datsheets', 'listing_status')) {
            return;
        }

        Schema::table('amazon_datsheets', function (Blueprint $table) {
            $table->dropColumn('listing_status');
        });
    }
};
