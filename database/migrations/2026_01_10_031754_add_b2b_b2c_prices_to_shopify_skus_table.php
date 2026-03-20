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
            $table->string('b2b_price', 191)->nullable()->after('price');
            $table->string('b2c_price', 191)->nullable()->after('b2b_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shopify_skus', function (Blueprint $table) {
            $table->dropColumn(['b2b_price', 'b2c_price']);
        });
    }
};
