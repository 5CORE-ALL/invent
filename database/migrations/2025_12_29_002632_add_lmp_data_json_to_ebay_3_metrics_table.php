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
            $table->json('lmp_data')->nullable()->after('lmp_link')->comment('All competitor prices [{price, link, title, seller}]');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ebay_3_metrics', function (Blueprint $table) {
            $table->dropColumn('lmp_data');
        });
    }
};
