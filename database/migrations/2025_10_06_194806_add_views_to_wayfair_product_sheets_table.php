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
            $table->integer('views')->nullable()->after('l60');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wayfair_product_sheets', function (Blueprint $table) {
            $table->dropColumn('views');
        });
    }
};
