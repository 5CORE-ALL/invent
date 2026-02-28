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
        Schema::table('amazon_listings_raw', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('manufacturer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amazon_listings_raw', function (Blueprint $table) {
            $table->dropColumn('brand');
        });
    }
};
