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
        if (! Schema::hasTable('amazon_datsheets')) {
            return;
        }
        if (Schema::hasColumn('amazon_datsheets', 'organic_views')) {
            return;
        }

        Schema::table('amazon_datsheets', function (Blueprint $table) {
            $table->integer('organic_views')->nullable()->after('price_lmpa');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('amazon_datsheets')) {
            return;
        }
        if (! Schema::hasColumn('amazon_datsheets', 'organic_views')) {
            return;
        }

        Schema::table('amazon_datsheets', function (Blueprint $table) {
            $table->dropColumn('organic_views');
        });
    }
};
