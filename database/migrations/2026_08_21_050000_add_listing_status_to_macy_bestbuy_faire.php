<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['macy_products', 'bestbuy_usa_products', 'faire_metric'] as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'listing_status')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('listing_status', 32)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['macy_products', 'bestbuy_usa_products', 'faire_metric'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'listing_status')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('listing_status');
            });
        }
    }
};
