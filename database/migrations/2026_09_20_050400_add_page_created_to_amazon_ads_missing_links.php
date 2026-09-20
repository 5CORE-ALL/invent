<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('amazon_ads_missing_links')) {
            return;
        }
        if (Schema::hasColumn('amazon_ads_missing_links', 'page_created')) {
            return;
        }

        Schema::table('amazon_ads_missing_links', function (Blueprint $table) {
            $table->boolean('page_created')->default(false)->after('user_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('amazon_ads_missing_links')) {
            return;
        }
        if (! Schema::hasColumn('amazon_ads_missing_links', 'page_created')) {
            return;
        }

        Schema::table('amazon_ads_missing_links', function (Blueprint $table) {
            $table->dropColumn('page_created');
        });
    }
};
