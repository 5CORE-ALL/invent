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
        Schema::table('amazon_search_raw_responses', function (Blueprint $table) {
            if (!Schema::hasColumn('amazon_search_raw_responses', 'page')) {
                $table->unsignedSmallInteger('page')->nullable()->after('marketplace');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('amazon_search_raw_responses', function (Blueprint $table) {
            if (Schema::hasColumn('amazon_search_raw_responses', 'page')) {
                $table->dropColumn('page');
            }
        });
    }
};
