<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopify_skus', function (Blueprint $table) {
            $table->integer('unavailable')->default(0)->after('on_hand');
            $table->integer('incoming')->default(0)->after('unavailable');
        });
    }

    public function down(): void
    {
        Schema::table('shopify_skus', function (Blueprint $table) {
            $table->dropColumn(['unavailable', 'incoming']);
        });
    }
};
