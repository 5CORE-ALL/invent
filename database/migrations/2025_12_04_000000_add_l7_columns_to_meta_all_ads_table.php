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
        Schema::table('meta_all_ads', function (Blueprint $table) {
            $table->bigInteger('imp_l7')->default(0)->after('clicks_l30');
            $table->decimal('spent_l7', 15, 2)->default(0)->after('imp_l7');
            $table->integer('clicks_l7')->default(0)->after('spent_l7');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meta_all_ads', function (Blueprint $table) {
            $table->dropColumn(['imp_l7', 'spent_l7', 'clicks_l7']);
        });
    }
};
