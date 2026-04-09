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
        if (! Schema::hasTable('wayfair_product_sheets')) {
            return;
        }
        if (Schema::hasColumn('wayfair_product_sheets', 'views')) {
            return;
        }

        Schema::table('wayfair_product_sheets', function (Blueprint $table) {
            $table->integer('views')->nullable()->after('l60');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('wayfair_product_sheets')) {
            return;
        }
        if (! Schema::hasColumn('wayfair_product_sheets', 'views')) {
            return;
        }

        Schema::table('wayfair_product_sheets', function (Blueprint $table) {
            $table->dropColumn('views');
        });
    }
};
