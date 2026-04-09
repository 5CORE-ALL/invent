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
        if (! Schema::hasTable('meta_all_ads')) {
            return;
        }
        if (Schema::hasColumn('meta_all_ads', 'ad_type')) {
            return;
        }

        Schema::table('meta_all_ads', function (Blueprint $table) {
            $table->string('ad_type')->nullable()->after('audience');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('meta_all_ads')) {
            return;
        }
        if (! Schema::hasColumn('meta_all_ads', 'ad_type')) {
            return;
        }

        Schema::table('meta_all_ads', function (Blueprint $table) {
            $table->dropColumn('ad_type');
        });
    }
};
