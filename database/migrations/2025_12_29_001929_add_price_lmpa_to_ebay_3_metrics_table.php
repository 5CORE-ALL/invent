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
        Schema::table('ebay_3_metrics', function (Blueprint $table) {
            $table->decimal('price_lmpa', 10, 2)->nullable()->after('views');
            $table->string('lmp_link', 500)->nullable()->after('price_lmpa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ebay_3_metrics', function (Blueprint $table) {
            $table->dropColumn(['price_lmpa', 'lmp_link']);
        });
    }
};
