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
        Schema::table('shopify_skus', function (Blueprint $table) {
            $table->integer('available_to_sell')->default(0)->after('image_src');
            $table->integer('committed')->default(0)->after('available_to_sell');
            $table->integer('on_hand')->default(0)->after('committed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopify_skus', function (Blueprint $table) {
            $table->dropColumn(['available_to_sell', 'committed', 'on_hand']);
        });
    }
};
