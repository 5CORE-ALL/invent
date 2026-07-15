<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ads_link_sku_fields')) {
            return;
        }

        Schema::table('ads_link_sku_fields', function (Blueprint $table) {
            if (! Schema::hasColumn('ads_link_sku_fields', 'spl_minus_kw')) {
                $table->json('spl_minus_kw')->nullable()->after('pt_spl');
            }
            if (! Schema::hasColumn('ads_link_sku_fields', 'spl_minus_pt')) {
                $table->json('spl_minus_pt')->nullable()->after('spl_minus_kw');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ads_link_sku_fields')) {
            return;
        }

        Schema::table('ads_link_sku_fields', function (Blueprint $table) {
            if (Schema::hasColumn('ads_link_sku_fields', 'spl_minus_kw')) {
                $table->dropColumn('spl_minus_kw');
            }
            if (Schema::hasColumn('ads_link_sku_fields', 'spl_minus_pt')) {
                $table->dropColumn('spl_minus_pt');
            }
        });
    }
};
