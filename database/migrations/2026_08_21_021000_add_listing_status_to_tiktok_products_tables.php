<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['tiktok_products', 'tiktok_products_two'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'listing_status')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->string('listing_status', 32)->nullable()->index();
                });
            }
            DB::table($table)->whereNull('listing_status')->update(['listing_status' => 'active']);
        }
    }

    public function down(): void
    {
        foreach (['tiktok_products', 'tiktok_products_two'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'listing_status')) {
                continue;
            }
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('listing_status');
            });
        }
    }
};
