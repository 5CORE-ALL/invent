<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('api_vs_sheet_settings')) {
            return;
        }

        Schema::table('api_vs_sheet_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('api_vs_sheet_settings', 'price_api_2w')) {
                $table->string('price_api_2w', 10)->nullable()->after('upload_source');
            }
            if (! Schema::hasColumn('api_vs_sheet_settings', 'price_api_2w_sheet_link')) {
                $table->string('price_api_2w_sheet_link', 2048)->nullable()->after('price_api_2w');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('api_vs_sheet_settings')) {
            return;
        }

        Schema::table('api_vs_sheet_settings', function (Blueprint $table) {
            if (Schema::hasColumn('api_vs_sheet_settings', 'price_api_2w_sheet_link')) {
                $table->dropColumn('price_api_2w_sheet_link');
            }
            if (Schema::hasColumn('api_vs_sheet_settings', 'price_api_2w')) {
                $table->dropColumn('price_api_2w');
            }
        });
    }
};
