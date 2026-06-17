<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('videos_for_ads', function (Blueprint $table) {
            $table->string('ads_status')->nullable()->default('Todo')->after('sku');
        });
    }

    public function down(): void
    {
        Schema::table('videos_for_ads', function (Blueprint $table) {
            $table->dropColumn('ads_status');
        });
    }
};
