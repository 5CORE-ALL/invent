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
        Schema::table('amazon_datasheets', function (Blueprint $table) {
            $table->string('listing_status')->nullable()->after('sold');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amazon_datasheets', function (Blueprint $table) {
            $table->dropColumn('listing_status');
        });
    }
};
