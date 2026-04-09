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
        if (! Schema::hasTable('walmart_pricing_sales')) {
            return;
        }
        if (Schema::hasColumn('walmart_pricing_sales', 'page_views')) {
            return;
        }

        Schema::table('walmart_pricing_sales', function (Blueprint $table) {
            $table->integer('page_views')->nullable()->after('views');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('walmart_pricing_sales')) {
            return;
        }
        if (! Schema::hasColumn('walmart_pricing_sales', 'page_views')) {
            return;
        }

        Schema::table('walmart_pricing_sales', function (Blueprint $table) {
            $table->dropColumn('page_views');
        });
    }
};


