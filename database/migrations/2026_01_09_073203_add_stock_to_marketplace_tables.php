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
        Schema::table('macy_products', function (Blueprint $table) {
            $table->integer('stock')->nullable()->after('price');
        });

        Schema::table('bestbuy_usa_products', function (Blueprint $table) {
            $table->integer('stock')->nullable()->after('price');
        });

        Schema::table('tiendamia_products', function (Blueprint $table) {
            $table->integer('stock')->nullable()->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('macy_products', function (Blueprint $table) {
            $table->dropColumn('stock');
        });

        Schema::table('bestbuy_usa_products', function (Blueprint $table) {
            $table->dropColumn('stock');
        });

        Schema::table('tiendamia_products', function (Blueprint $table) {
            $table->dropColumn('stock');
        });
    }
};
