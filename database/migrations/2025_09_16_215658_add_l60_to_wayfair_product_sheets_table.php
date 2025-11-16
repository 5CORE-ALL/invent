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
        Schema::table('wayfair_product_sheets', function (Blueprint $table) {
            $table->integer('l60')->nullable()->after('l30');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wayfair_product_sheets', function (Blueprint $table) {
            $table->dropColumn('l60');
        });
    }
};
